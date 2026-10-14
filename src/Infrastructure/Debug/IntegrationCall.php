<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Debug;

/**
 * One recorded outgoing call, captured by TracingMiddleware and exposed to the
 * profiler template by IntegrationEngineDataCollector.
 *
 * error contains only an exception class name. Exception messages are never
 * persisted because they may contain upstream response bodies or secrets.
 * bodyClass is likewise only the action body's class name, never its contents.
 *
 * bodyData, contextData and responseData are null unless the integration opts
 * in via profiler_payloads (see ADR 0019) — and, even then, only while
 * kernel.debug is true. responseData is only ever populated on success: a
 * failed call's upstream response/error body is never captured, matching the
 * existing rule for error messages.
 */
final readonly class IntegrationCall
{
    /**
     * @param null|array<mixed> $bodyData
     * @param null|array<mixed> $contextData
     * @param null|array<mixed> $responseData
     */
    public function __construct(
        public string $integrationName,
        public string $actionName,
        public string $method,
        public string $path,
        public float $durationMs,
        public ?string $error,
        public ?int $statusCode,
        public bool $cached = false,
        public ?string $bodyClass = null,
        public ?array $bodyData = null,
        public ?array $contextData = null,
        public ?array $responseData = null,
    ) {}
}
