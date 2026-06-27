<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Client;

use IntegrationEngine\Core\Batch\PreparedRequest;

abstract class AbstractClientMiddleware implements ClientMiddlewareInterface
{
    /**
     * Default: pass the batch through unchanged. Override for batch-aware
     * behaviour (e.g. cache hit/miss splitting, batch-level tracing).
     *
     * @param array<array-key, PreparedRequest>                                                      $requests
     * @param callable(array<array-key, PreparedRequest>): array<array-key, array<mixed>|\Throwable> $next
     *
     * @return array<array-key, array<mixed>|\Throwable>
     */
    public function processMany(array $requests, callable $next): array
    {
        return $next($requests);
    }
}
