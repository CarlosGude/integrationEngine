<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Webhook;

use IntegrationEngine\Core\Webhook\WebhookFailure;

/**
 * Port for webhook dead-letter queue (DLQ) storage.
 *
 * Implementations store failed webhooks for manual or automatic retry.
 *
 * @author Carlos Gude
 */
interface WebhookDlqPort
{
    /**
     * Store a failed webhook in the DLQ.
     *
     * @param WebhookFailure $failure The failed webhook details
     *
     * @throws \RuntimeException If storage fails
     */
    public function store(WebhookFailure $failure): void;

    /**
     * Retrieve a failure by ID.
     *
     * @param string $failureId The failure ID (UUID)
     *
     * @return null|WebhookFailure The failure, or null if not found
     */
    public function findById(string $failureId): ?WebhookFailure;

    /**
     * Get all unresolved failures (sorted by creation time, oldest first).
     *
     * @return array<int, WebhookFailure>
     */
    public function findUnresolved(): array;

    /**
     * Mark a failure as resolved (remove from DLQ).
     *
     * @param string $failureId The failure ID
     *
     * @return bool True if removed, false if not found
     */
    public function resolve(string $failureId): bool;

    /**
     * Record a retry attempt for a failure.
     *
     * @param WebhookFailure $failure Updated failure with retry_count incremented
     *
     * @throws \RuntimeException If update fails
     */
    public function recordRetry(WebhookFailure $failure): void;

    /**
     * Get failure count (for monitoring).
     *
     * @return int Total unresolved failures
     */
    public function countUnresolved(): int;
}
