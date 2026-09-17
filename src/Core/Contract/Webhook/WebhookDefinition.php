<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Webhook;

/**
 * Immutable definition of an inbound webhook event type and its handler.
 *
 * @author Carlos Gude
 */
final readonly class WebhookDefinition
{
    /**
     * @param string          $eventType   Event type from provider (e.g., 'charge.succeeded')
     * @param class-string    $mapperClass Mapper class that handles this event
     * @param SignatureConfig $signature   Signature verification config
     */
    public function __construct(
        private readonly string $eventType,
        private readonly string $mapperClass,
        private readonly SignatureConfig $signature,
    ) {}

    public function getEventType(): string
    {
        return $this->eventType;
    }

    /**
     * Mapper class that handles this event.
     *
     * @return class-string
     */
    public function getMapperClass(): string
    {
        return $this->mapperClass;
    }

    public function getSignature(): SignatureConfig
    {
        return $this->signature;
    }
}
