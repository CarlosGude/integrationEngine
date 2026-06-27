<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Cache;

use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Client\AbstractClientMiddleware;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use IntegrationEngine\Core\Port\CachePort;
use IntegrationEngine\Infrastructure\Debug\IntegrationEngineDataCollector;

/**
 * Caches raw HTTP responses when cache_ttl is set on the action.
 * Actions without cache_ttl pass through with zero overhead.
 * In sendMany(), cache hits are resolved before dispatching — only misses
 * reach the inner adapter, preserving concurrency for uncached requests.
 */
final class CachingMiddleware extends AbstractClientMiddleware
{
    public function __construct(
        private readonly CachePort $cache,
        private readonly string $integrationName,
        private readonly ?IntegrationEngineDataCollector $collector = null,
    ) {}

    /** @return array<mixed> */
    public function process(
        AbstractAction $action,
        ?ActionContextInterface $context,
        ?RequestHeadersInterface $headers,
        callable $next,
    ): array {
        $ttl = $action->getCacheTtl();

        if (null === $ttl) {
            return $next($action, $context, $headers);
        }

        $key = $this->buildKey($action, $context, $headers);

        /** @var null|array<mixed> $cached */
        $cached = $this->cache->get($key);

        if (null !== $cached) {
            $this->recordHit($action);

            return $cached;
        }

        $result = $next($action, $context, $headers);
        $this->cache->set($key, $result, $ttl);

        return $result;
    }

    /**
     * @param array<array-key, PreparedRequest>                                                      $requests
     * @param callable(array<array-key, PreparedRequest>): array<array-key, array<mixed>|\Throwable> $next
     *
     * @return array<array-key, array<mixed>|\Throwable>
     */
    public function processMany(array $requests, callable $next): array
    {
        $hits = [];
        $misses = [];

        foreach ($requests as $key => $request) {
            $ttl = $request->action->getCacheTtl();

            if (null === $ttl) {
                $misses[$key] = $request;

                continue;
            }

            $cacheKey = $this->buildKey($request->action, $request->context, $request->headers);

            /** @var null|array<mixed> $cached */
            $cached = $this->cache->get($cacheKey);

            if (null !== $cached) {
                $hits[$key] = $cached;
                $this->recordHit($request->action);
            } else {
                $misses[$key] = $request;
            }
        }

        $results = $hits;

        if ([] !== $misses) {
            foreach ($next($misses) as $key => $result) {
                if (!$result instanceof \Throwable) {
                    $ttl = $misses[$key]->action->getCacheTtl();
                    if (null !== $ttl) {
                        $this->cache->set(
                            $this->buildKey($misses[$key]->action, $misses[$key]->context, $misses[$key]->headers),
                            $result,
                            $ttl,
                        );
                    }
                }
                $results[$key] = $result;
            }
        }

        return $results;
    }

    public function buildKey(
        AbstractAction $action,
        ?ActionContextInterface $context,
        ?RequestHeadersInterface $headers,
    ): string {
        return \sprintf(
            'ie_response_%s_%s',
            $this->integrationName,
            sha1(serialize([
                $action::class,
                $context?->toArray() ?? [],
                $headers?->toArray() ?? [],
            ])),
        );
    }

    private function recordHit(AbstractAction $action): void
    {
        $this->collector?->recordCall(
            integrationName: $this->integrationName,
            actionName: $action::getName(),
            method: $action->getMethod(),
            path: $action->getRawPath(),
            durationMs: 0.0,
            error: null,
            cached: true,
        );
    }
}
