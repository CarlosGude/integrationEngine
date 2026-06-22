<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Debug;

/**
 * One recorded outgoing call, captured by TraceableClient and exposed to
 * the profiler template by IntegrationEngineDataCollector.
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
    ) {}
}
