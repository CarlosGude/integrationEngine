<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Batch;

use IntegrationEngine\Core\Contract\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Core\Port\CachePort;

/**
 * Tracks which batch items entered with a pre-cached dynamic auth token
 * (retryable on 401) vs which tokens were first fetched during this batch
 * (already fresh — no retry).
 *
 * Lifecycle inside sendMany():
 *   1. observe($key, $auth)  — before the token is resolved, for every dynamic-auth item
 *   2. withStaticToken(...)  — resolves (and may cache) the token
 *   3. plan($raw)            — after dispatch: identifies 401s, drops stale cache entries
 */
final class BatchTokenRetry
{
    /** @var array<array-key, DynamicAuthorizationConfig> */
    private array $retryable = [];

    /** @var list<string> */
    private array $fetchedInBatch = [];

    public function __construct(
        private readonly CachePort $cache,
        private readonly string $integrationName,
    ) {}

    /**
     * Must be called before the token is resolved for this item.
     * A token already in cache (but not fetched by this batch) is retryable.
     * A token that will be fetched now counts as fresh — 401s are final.
     */
    public function observe(int|string $key, DynamicAuthorizationConfig $auth): void
    {
        $cacheKey = $this->cacheKey($auth);

        if (\is_string($this->cache->get($cacheKey))) {
            if (!\in_array($cacheKey, $this->fetchedInBatch, true)) {
                $this->retryable[$key] = $auth;
            }
        } else {
            $this->fetchedInBatch[] = $cacheKey;
        }
    }

    /**
     * After dispatch: returns the subset of items that received HTTP 401 and
     * hold a retryable cached token, and drops those stale tokens from cache.
     * Multiple items sharing one token action share a single cache deletion.
     *
     * @param array<array-key, array<mixed>|\Throwable> $raw
     *
     * @return array<array-key, DynamicAuthorizationConfig>
     */
    public function plan(array $raw): array
    {
        $toRetry = [];

        foreach ($this->retryable as $key => $auth) {
            $result = $raw[$key] ?? null;

            if ($result instanceof RequestResponseException && 401 === $result->statusCode) {
                $toRetry[$key] = $auth;
            }
        }

        $dropped = [];

        foreach ($toRetry as $auth) {
            $cacheKey = $this->cacheKey($auth);

            if (!\in_array($cacheKey, $dropped, true)) {
                $this->cache->delete($cacheKey);
                $dropped[] = $cacheKey;
            }
        }

        return $toRetry;
    }

    private function cacheKey(DynamicAuthorizationConfig $auth): string
    {
        return \sprintf('integration_engine.token.%s.%s', $this->integrationName, $auth->action);
    }
}
