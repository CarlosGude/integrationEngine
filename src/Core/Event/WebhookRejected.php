<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Event;

/** Immutable observability metadata; never carries request/response objects or exceptions. */
final readonly class WebhookRejected
{
    public function __construct(
        public string $integrationName,
        public string $reason,
        public float $timestamp,
    ) {}
}
