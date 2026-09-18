<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Webhook;

/**
 * Webhook event lifecycle states.
 *
 * Tracks the progression of a webhook from reception to final resolution.
 *
 * @author Carlos Gude
 */
enum WebhookEventState: string
{
    /**
     * Webhook received but not yet validated.
     */
    case RECEIVED = 'received';

    /**
     * Webhook signature being validated.
     */
    case VALIDATING = 'validating';

    /**
     * Webhook processing in progress.
     */
    case PROCESSING = 'processing';

    /**
     * Webhook processed successfully.
     */
    case SUCCESS = 'success';

    /**
     * Webhook processing failed.
     */
    case FAILED = 'failed';

    /**
     * Webhook retry in progress (after failure).
     */
    case RETRYING = 'retrying';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::RECEIVED => 'Received',
            self::VALIDATING => 'Validating',
            self::PROCESSING => 'Processing',
            self::SUCCESS => 'Success',
            self::FAILED => 'Failed',
            self::RETRYING => 'Retrying',
        };
    }

    /**
     * Check if state is terminal (no further transitions possible).
     */
    public function isTerminal(): bool
    {
        return self::SUCCESS === $this || self::FAILED === $this;
    }
}
