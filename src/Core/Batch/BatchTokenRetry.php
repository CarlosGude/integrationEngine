<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Batch;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Auth\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Core\Port\CachePort;

/**
 * Tracks which batch items entered with a pre-cached dynamic auth token
 * (retryable on 401) vs which tokens were first fetched during this batch
 * (already fresh — no retry).
 *
 * Lifecycle inside sendMany():
 *   1. prepareWithToken($key, $auth, $cacheDiscriminator, $factory) — snapshots cache
 *      state, then resolves the token by calling $factory(); the factory cannot be
 *      reordered because it runs inside this method
 *   2. plan($raw) — after dispatch: identifies 401s, drops stale cache entries
 */
final class BatchTokenRetry
{
    /** @var array<array-key, array{auth: DynamicAuthorizationConfig, cacheDiscriminator: ?string}> */
    private array $retryable = [];

    /** @var array<string, true> */
    private array $fetchedInBatch = [];

    public function __construct(
        private readonly CachePort $cache,
        private readonly string $integrationName,
    ) {}

    /**
     * Snapshots the token's pre-fetch cache state for this item, then calls
     * $factory() to resolve the token (which may write to the cache).
     * Keeps the observe-then-resolve order structural rather than documental.
     *
     * $cacheDiscriminator must match what the factory resolves the token
     * against — DynamicAuthorizationConfig::cacheKey() namespaces by it, so
     * a mismatch here would make this class check one connection's cache
     * entry while the factory reads/writes another's. It's whatever stably
     * identifies the connection: a connectionId when the engine resolved
     * one, the per-call baseUrl otherwise.
     *
     * @param callable(): AbstractAction $factory
     */
    public function prepareWithToken(
        int|string $key,
        DynamicAuthorizationConfig $auth,
        ?string $cacheDiscriminator,
        callable $factory,
    ): AbstractAction {
        $cacheKey = $this->cacheKey($auth, $cacheDiscriminator);
        $isPreCached = \is_string($this->cache->get($cacheKey));

        if ($isPreCached && !isset($this->fetchedInBatch[$cacheKey])) {
            $this->retryable[$key] = ['auth' => $auth, 'cacheDiscriminator' => $cacheDiscriminator];
        }

        $action = ($factory)();

        if (!$isPreCached) {
            $this->fetchedInBatch[$cacheKey] = true;
        }

        return $action;
    }

    /**
     * After dispatch: returns the subset of items that received HTTP 401 and
     * hold a retryable cached token, and drops those stale tokens from cache.
     * Items sharing one token action AND one cache discriminator share a
     * single cache deletion; the same action against a different connection
     * is dropped separately since each holds its own cache entry.
     *
     * @param array<array-key, array{body: array<mixed>, headers: array<string, list<string>>, statusCode?: int}|\Throwable> $raw
     *
     * @return array<array-key, DynamicAuthorizationConfig>
     */
    public function plan(array $raw): array
    {
        $toRetry = [];

        foreach ($this->retryable as $key => $entry) {
            $result = $raw[$key] ?? null;

            if ($result instanceof RequestResponseException && 401 === $result->statusCode) {
                $toRetry[$key] = $entry['auth'];
            }
        }

        /** @var array<string, true> $dropped */
        $dropped = [];

        foreach ($toRetry as $key => $auth) {
            $cacheKey = $this->cacheKey($auth, $this->retryable[$key]['cacheDiscriminator']);

            if (!isset($dropped[$cacheKey])) {
                $this->cache->delete($cacheKey);
                $dropped[$cacheKey] = true;
            }
        }

        return $toRetry;
    }

    private function cacheKey(DynamicAuthorizationConfig $auth, ?string $cacheDiscriminator): string
    {
        return $auth->cacheKey($this->integrationName, $cacheDiscriminator);
    }
}
