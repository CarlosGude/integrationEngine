<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Debug;

use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Client\AbstractClientMiddleware;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use IntegrationEngine\Core\Exception\RequestResponseException;

/**
 * Records every outgoing call — timing, action metadata, and errors — in the
 * Symfony Profiler data collector. Only wired when kernel.debug is true and
 * the web-profiler-bundle is active. Sits inside CachingMiddleware so it only
 * fires for requests that actually reach the HTTP adapter, not for cache hits.
 */
final class TracingMiddleware extends AbstractClientMiddleware
{
    public function __construct(
        private readonly string $integrationName,
        private readonly IntegrationEngineDataCollector $collector,
    ) {}

    /** @return array<mixed> */
    public function process(
        AbstractAction $action,
        ?ActionContextInterface $context,
        ?RequestHeadersInterface $headers,
        callable $next,
    ): array {
        $start = microtime(true);
        $error = null;

        try {
            return $next($action, $context, $headers);
        } catch (\Throwable $e) {
            $error = $e;

            throw $e;
        } finally {
            $this->collector->recordCall(
                integrationName: $this->integrationName,
                actionName: $action::getName(),
                method: $action->getMethod(),
                path: $action->getRawPath(),
                durationMs: (microtime(true) - $start) * 1000,
                error: $error,
                statusCode: $error instanceof RequestResponseException ? $error->statusCode : null,
            );
        }
    }

    /**
     * @param array<array-key, PreparedRequest>                                                      $requests
     * @param callable(array<array-key, PreparedRequest>): array<array-key, array<mixed>|\Throwable> $next
     *
     * @return array<array-key, array<mixed>|\Throwable>
     */
    public function processMany(array $requests, callable $next): array
    {
        $start = microtime(true);
        $results = $next($requests);
        $durationMs = (microtime(true) - $start) * 1000;

        foreach ($requests as $key => $request) {
            $result = $results[$key] ?? null;
            $error = $result instanceof \Throwable ? $result : null;

            $this->collector->recordCall(
                integrationName: $this->integrationName,
                actionName: $request->action::getName(),
                method: $request->action->getMethod(),
                path: $request->action->getRawPath(),
                durationMs: $durationMs,
                error: $error,
                statusCode: $error instanceof RequestResponseException ? $error->statusCode : null,
            );
        }

        return $results;
    }
}
