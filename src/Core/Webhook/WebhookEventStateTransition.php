<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Webhook;

/**
 * Represents a state transition in a webhook's lifecycle.
 *
 * Immutable record of: from state → to state + timestamp + context.
 *
 * @author Carlos Gude
 */
final readonly class WebhookEventStateTransition
{
    /**
     * @param string               $webhookId    The webhook/event ID
     * @param WebhookEventState    $fromState    Previous state
     * @param WebhookEventState    $toState      New state
     * @param \DateTimeImmutable   $transitionAt When the transition occurred
     * @param null|string          $reason       Optional reason (e.g., "Signature mismatch", "Processing error")
     * @param array<string, mixed> $metadata     Optional context (e.g., error details, attempt number)
     */
    public function __construct(
        public string $webhookId,
        public WebhookEventState $fromState,
        public WebhookEventState $toState,
        public \DateTimeImmutable $transitionAt,
        public ?string $reason = null,
        public array $metadata = [],
    ) {}

    /**
     * Create a successful processing transition.
     */
    public static function success(string $webhookId, \DateTimeImmutable $transitionAt): self
    {
        return new self(
            webhookId: $webhookId,
            fromState: WebhookEventState::PROCESSING,
            toState: WebhookEventState::SUCCESS,
            transitionAt: $transitionAt,
        );
    }

    /**
     * Create a failed processing transition.
     *
     * @param array<string, mixed> $metadata Optional metadata
     */
    public static function failed(
        string $webhookId,
        \DateTimeImmutable $transitionAt,
        string $reason,
        array $metadata = [],
    ): self {
        return new self(
            webhookId: $webhookId,
            fromState: WebhookEventState::PROCESSING,
            toState: WebhookEventState::FAILED,
            transitionAt: $transitionAt,
            reason: $reason,
            metadata: $metadata,
        );
    }

    /**
     * Get a human-readable description of this transition.
     */
    public function description(): string
    {
        $desc = "{$this->fromState->label()} → {$this->toState->label()}";

        if (null !== $this->reason) {
            $desc .= " ({$this->reason})";
        }

        return $desc;
    }
}
