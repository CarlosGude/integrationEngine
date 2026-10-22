<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Event;

/** Immutable observability metadata; never carries request/response objects or exceptions. */
final readonly class ResponseReceived
{
    public function __construct(
        public string $integrationName,
        public string $action,
        public float $durationMs,
        public int $statusCode,
        public float $timestamp,
        public int|string|null $requestKey = null,
    ) {}
}
