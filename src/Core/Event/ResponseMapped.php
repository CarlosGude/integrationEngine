<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Event;

/** Immutable observability metadata; never carries request/response objects or exceptions. */
final readonly class ResponseMapped
{
    public function __construct(
        public string $integrationName,
        public string $action,
        public float $durationMs,
        public int $statusCode,
        public string $responseClass,
        public float $timestamp,
        public int|string|null $requestKey = null,
    ) {}
}
