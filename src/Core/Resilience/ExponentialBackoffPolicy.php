<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Resilience;

use IntegrationEngine\Core\Contract\Action\AbstractAction;

/**
 * Exponential backoff retry policy.
 *
 * Retries transient errors with increasing delays:
 * Attempt 1: wait 100ms
 * Attempt 2: wait 200ms
 * Attempt 3: wait 400ms
 * Attempt 4: wait 800ms
 * etc.
 *
 * Allows 3 retries by default, numbered from 1. This policy only makes
 * decisions; the caller owns execution, waiting, and invoking the fallback.
 */
final class ExponentialBackoffPolicy implements ResiliencePolicyInterface
{
    public function __construct(
        private readonly int $maxAttempts = 3,
        private readonly int $initialBackoffMs = 100,
    ) {
        if ($maxAttempts < 1) {
            throw new \InvalidArgumentException('maxAttempts must be >= 1');
        }
        if ($initialBackoffMs < 0) {
            throw new \InvalidArgumentException('initialBackoffMs must be >= 0');
        }
    }

    public function shouldRetry(\Throwable $e, int $attempt): bool
    {
        $this->validateAttempt($attempt);

        if ($attempt > $this->maxAttempts) {
            return false; // Max attempts reached
        }

        return ErrorClassifier::isTransient($e);
    }

    public function getBackoffMs(int $attempt): int
    {
        $this->validateAttempt($attempt);

        if (0 === $this->initialBackoffMs) {
            return 0;
        }

        if ($attempt >= \PHP_INT_SIZE * 8) {
            throw new \OverflowException('Backoff delay exceeds the integer range.');
        }

        $multiplier = 1 << ($attempt - 1);
        if ($this->initialBackoffMs > intdiv(PHP_INT_MAX, $multiplier)) {
            throw new \OverflowException('Backoff delay exceeds the integer range.');
        }

        return $this->initialBackoffMs * $multiplier;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getFallback(AbstractAction $action, \Throwable $e): mixed
    {
        // No fallback: throw the exception
        throw $e;
    }

    public function getName(): string
    {
        return 'exponential_backoff';
    }

    private function validateAttempt(int $attempt): void
    {
        if ($attempt < 1) {
            throw new \InvalidArgumentException('attempt must be >= 1');
        }
    }
}
