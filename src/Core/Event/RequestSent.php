<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Event;

/** Immutable observability metadata; never carries request/response objects or exceptions. */
final readonly class RequestSent
{
    public function __construct(
        public string $integrationName,
        public string $action,
        public string $method,
        public string $path,
        public float $timestamp,
        public ?string $connectionId = null,
        public int|string|null $requestKey = null,
    ) {}
}
