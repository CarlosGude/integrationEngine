<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook\Message;

/**
 * Messenger message to process a webhook asynchronously.
 *
 * When a webhook is received, it's dispatched as a message to be processed
 * by the message bus (e.g., via background workers).
 *
 * @author Carlos Gude
 */
final class ProcessWebhookMessage
{
    /**
     * @param string                $eventType The webhook event type
     * @param array<string, mixed>  $payload   The webhook payload
     * @param array<string, string> $headers   HTTP headers from the webhook request
     */
    public function __construct(
        public readonly string $eventType,
        public readonly array $payload,
        public readonly array $headers,
    ) {}
}
