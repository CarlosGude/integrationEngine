<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Event;

/** Immutable observability metadata; never carries request/response objects or exceptions. */
final readonly class WebhookReceived
{
    public function __construct(
        public string $integrationName,
        public string $eventType,
        public string $eventId,
        public float $timestamp,
    ) {}
}
