<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use IntegrationEngine\Core\Contract\Webhook\WebhookIdempotencyPort;

/**
 * In-memory webhook idempotency adapter for testing.
 *
 * Stores fingerprints and timestamps in memory.
 *
 * @author Carlos Gude
 */
final class WebhookIdempotencyAdapter implements WebhookIdempotencyPort
{
    /**
     * @var array<string, array{eventType: string, timestamp: \DateTimeImmutable}>
     */
    private array $fingerprints = [];

    public function isProcessed(string $fingerprint): bool
    {
        return isset($this->fingerprints[$fingerprint]);
    }

    public function markProcessed(
        string $fingerprint,
        string $eventType,
        array $payload,
        \DateTimeImmutable $timestamp,
    ): void {
        $this->fingerprints[$fingerprint] = [
            'eventType' => $eventType,
            'timestamp' => $timestamp,
        ];
    }

    public function cleanup(int $retentionSeconds = 86400): void
    {
        $cutoff = new \DateTimeImmutable("now - {$retentionSeconds} seconds");

        foreach ($this->fingerprints as $fingerprint => $data) {
            if ($data['timestamp'] < $cutoff) {
                unset($this->fingerprints[$fingerprint]);
            }
        }
    }

    /**
     * Clear all fingerprints (for testing).
     */
    public function clear(): void
    {
        $this->fingerprints = [];
    }
}
