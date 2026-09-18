<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use IntegrationEngine\Core\Contract\Webhook\WebhookEventAuditPort;
use IntegrationEngine\Core\Webhook\WebhookEventState;
use IntegrationEngine\Core\Webhook\WebhookEventStateTransition;

/**
 * In-memory webhook event audit trail adapter for testing.
 *
 * @author Carlos Gude
 */
final class WebhookEventAuditAdapter implements WebhookEventAuditPort
{
    /** @var array<string, array<int, WebhookEventStateTransition>> */
    private array $history = [];

    public function recordTransition(WebhookEventStateTransition $transition): void
    {
        if (!isset($this->history[$transition->webhookId])) {
            $this->history[$transition->webhookId] = [];
        }

        $this->history[$transition->webhookId][] = $transition;
    }

    public function getCurrentState(string $webhookId): ?WebhookEventState
    {
        if (!isset($this->history[$webhookId]) || empty($this->history[$webhookId])) {
            return null;
        }

        $transitions = $this->history[$webhookId];

        return $transitions[\count($transitions) - 1]->toState;
    }

    public function getTransitionHistory(string $webhookId): array
    {
        return $this->history[$webhookId] ?? [];
    }

    public function getTransitionsFromState(string $webhookId, WebhookEventState $state): array
    {
        if (!isset($this->history[$webhookId])) {
            return [];
        }

        $filtered = [];
        foreach ($this->history[$webhookId] as $transition) {
            if ($transition->fromState === $state) {
                $filtered[] = $transition;
            }
        }

        return $filtered;
    }

    /**
     * Clear all transitions (for testing).
     */
    public function clear(): void
    {
        $this->history = [];
    }
}
