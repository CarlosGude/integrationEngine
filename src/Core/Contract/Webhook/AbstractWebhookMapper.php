<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Webhook;

/**
 * Base class for webhook event mappers.
 *
 * Similar to AbstractMapper for responses, but handles inbound webhook payloads.
 *
 * Each mapper must declare which webhook definition it handles via getDefinition().
 * The engine validates this invariant at map-time: a mapper's definition must
 * match the webhook definition that produced the RemoteEvent.
 *
 * @author Carlos Gude
 */
abstract class AbstractWebhookMapper
{
    /**
     * Get the webhook definition this mapper handles.
     *
     * Must return the exact event type (e.g., 'charge.succeeded') that this
     * mapper is responsible for.
     *
     * @return string Event type from the webhook definition
     */
    abstract public function getDefinition(): string;

    /**
     * Map a webhook payload to a typed event DTO.
     *
     * @param array<string, mixed> $payload The parsed webhook payload
     * @param array<string, mixed> $headers The webhook request headers
     *
     * @return WebhookEventInterface A typed, immutable event object
     */
    abstract public function map(array $payload, array $headers): WebhookEventInterface;
}
