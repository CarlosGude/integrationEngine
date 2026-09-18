<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Webhook;

/**
 * Port for webhook idempotency/replay detection.
 *
 * Implementations store webhook fingerprints to detect and prevent
 * duplicate processing within a time window.
 *
 * @author Carlos Gude
 */
interface WebhookIdempotencyPort
{
    /**
     * Check if a webhook fingerprint has been seen recently.
     *
     * @param string $fingerprint Unique identifier for the webhook (event_type:timestamp:hash)
     *
     * @return bool True if already processed, false if new
     */
    public function isProcessed(string $fingerprint): bool;

    /**
     * Record that a webhook fingerprint has been processed.
     *
     * @param string               $fingerprint Unique identifier
     * @param string               $eventType   Event type (e.g., 'products/update')
     * @param array<string, mixed> $payload     The webhook payload
     * @param \DateTimeImmutable   $timestamp   When the webhook was received
     *
     * @throws \RuntimeException If storage fails
     */
    public function markProcessed(
        string $fingerprint,
        string $eventType,
        array $payload,
        \DateTimeImmutable $timestamp,
    ): void;

    /**
     * Clean up old fingerprints (older than retention window).
     *
     * Webhooks older than the retention window are considered "replay window expired"
     * and will be rejected if retried.
     *
     * @param int $retentionSeconds How long to keep fingerprints (default: 86400 = 24h)
     */
    public function cleanup(int $retentionSeconds = 86400): void;
}
