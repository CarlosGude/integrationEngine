<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook;

use IntegrationEngine\Core\Contract\Webhook\WebhookIdempotencyPort;

/**
 * Manages webhook idempotency and replay detection.
 *
 * Generates fingerprints for each webhook and checks if it's already been
 * processed within the retention window. Silently skips duplicates.
 *
 * @author Carlos Gude
 */
final class WebhookIdempotencyService
{
    private int $retentionSeconds = 86400; // 24 hours

    public function __construct(
        private WebhookIdempotencyPort $idempotencyPort,
        private WebhookFingerprinter $fingerprinter,
    ) {}

    /**
     * Set the retention window for webhook fingerprints.
     *
     * @param int $seconds Seconds to keep fingerprints (default: 86400 = 24h)
     */
    public function setRetentionSeconds(int $seconds): void
    {
        $this->retentionSeconds = $seconds;
    }

    /**
     * Check if a webhook is a duplicate and should be skipped.
     *
     * @param string               $eventType Event type (e.g., 'products/update')
     * @param array<string, mixed> $payload   The webhook payload
     * @param \DateTimeImmutable   $timestamp When the webhook was received
     *
     * @return bool True if this is a duplicate (should be skipped), false if new
     */
    public function isDuplicate(
        string $eventType,
        array $payload,
        \DateTimeImmutable $timestamp,
    ): bool {
        $fingerprint = $this->fingerprinter->fingerprint($eventType, $payload, $timestamp);

        return $this->idempotencyPort->isProcessed($fingerprint);
    }

    /**
     * Record that a webhook has been processed.
     *
     * @param string               $eventType Event type
     * @param array<string, mixed> $payload   The webhook payload
     * @param \DateTimeImmutable   $timestamp When the webhook was received
     */
    public function recordProcessed(
        string $eventType,
        array $payload,
        \DateTimeImmutable $timestamp,
    ): void {
        $fingerprint = $this->fingerprinter->fingerprint($eventType, $payload, $timestamp);
        $this->idempotencyPort->markProcessed($fingerprint, $eventType, $payload, $timestamp);
    }

    /**
     * Clean up old fingerprints outside the retention window.
     */
    public function cleanup(): void
    {
        $this->idempotencyPort->cleanup($this->retentionSeconds);
    }
}
