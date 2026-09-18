<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Webhook;

use IntegrationEngine\Core\Webhook\WebhookEventState;
use IntegrationEngine\Core\Webhook\WebhookEventStateTransition;

/**
 * Port for webhook event audit trail storage.
 *
 * Implementations persist state transitions for debugging and compliance.
 *
 * @author Carlos Gude
 */
interface WebhookEventAuditPort
{
    /**
     * Record a state transition.
     *
     * @param WebhookEventStateTransition $transition The state change to record
     *
     * @throws \RuntimeException If storage fails
     */
    public function recordTransition(WebhookEventStateTransition $transition): void;

    /**
     * Get the current state of a webhook event.
     *
     * @param string $webhookId The webhook/event ID
     *
     * @return null|WebhookEventState The current state, or null if not found
     */
    public function getCurrentState(string $webhookId): ?WebhookEventState;

    /**
     * Get the full audit trail for a webhook event (ordered oldest → newest).
     *
     * @param string $webhookId The webhook/event ID
     *
     * @return array<int, WebhookEventStateTransition>
     */
    public function getTransitionHistory(string $webhookId): array;

    /**
     * Get transitions from a specific state.
     *
     * @param string            $webhookId The webhook/event ID
     * @param WebhookEventState $state     Filter by this state
     *
     * @return array<int, WebhookEventStateTransition>
     */
    public function getTransitionsFromState(string $webhookId, WebhookEventState $state): array;
}
