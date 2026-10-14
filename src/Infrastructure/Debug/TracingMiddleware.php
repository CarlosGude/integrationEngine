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
 *
 * $capturePayloads additionally records body, context and response contents
 * (never headers/credentials) when the integration opts in via
 * profiler_payloads (see ADR 0019). It is irrelevant on its own: this class
 * is only ever wired into the container when kernel.debug is already true,
 * so there is no code path where payloads are captured outside debug mode.
 */
final class TracingMiddleware extends AbstractClientMiddleware
{
    public function __construct(
        private readonly string $integrationName,
        private readonly IntegrationEngineDataCollector $collector,
        private readonly bool $capturePayloads = false,
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
        $body = $action->getBody();
        $result = null;

        try {
            $result = $next($action, $context, $headers);

            return $result;
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
                bodyClass: null !== $body ? $body::class : null,
                bodyData: $this->capturePayloads ? $body?->toArray() : null,
                contextData: $this->capturePayloads ? $context?->toArray() : null,
                responseData: $this->capturePayloads ? $this->extractResponseBody($result) : null,
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
        $batchDurationMs = (microtime(true) - $start) * 1000;

        // duration exists. Splitting the batch total evenly keeps aggregate
        // metrics (e.g. getTotalDurationMs()) truthful instead of counting
        // the same wall-clock time once per item.
        $durationMs = $batchDurationMs / max(1, \count($requests));

        foreach ($requests as $key => $request) {
            $result = $results[$key] ?? null;
            $error = $result instanceof \Throwable ? $result : null;
            $body = $request->action->getBody();

            $this->collector->recordCall(
                integrationName: $this->integrationName,
                actionName: $request->action::getName(),
                method: $request->action->getMethod(),
                path: $request->action->getRawPath(),
                durationMs: $durationMs,
                error: $error,
                statusCode: $error instanceof RequestResponseException ? $error->statusCode : null,
                bodyClass: null !== $body ? $body::class : null,
                bodyData: $this->capturePayloads ? $body?->toArray() : null,
                contextData: $this->capturePayloads ? $request->context?->toArray() : null,
                responseData: $this->capturePayloads ? $this->extractResponseBody($result) : null,
            );
        }

        return $results;
    }

    /** @return null|array<mixed> */
    private function extractResponseBody(mixed $result): ?array
    {
        if (!\is_array($result) || !\is_array($result['body'] ?? null)) {
            return null;
        }

        return $result['body'];
    }
}
