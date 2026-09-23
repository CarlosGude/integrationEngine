<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Debug;

/**
 * One recorded outgoing call, captured by TracingMiddleware and exposed to the
 * profiler template by IntegrationEngineDataCollector.
 *
 * error contains only an exception class name. Exception messages are never
 * persisted because they may contain upstream response bodies or secrets.
 */
final readonly class IntegrationCall
{
    public function __construct(
        public string $integrationName,
        public string $actionName,
        public string $method,
        public string $path,
        public float $durationMs,
        public ?string $error,
        public ?int $statusCode,
        public bool $cached = false,
    ) {}
}
