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
 * Max 3 retries by default. After exhaustion, throws exception.
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
        if ($attempt >= $this->maxAttempts) {
            return false; // Max attempts reached
        }

        return ErrorClassifier::isTransient($e);
    }

    public function getBackoffMs(int $attempt): int
    {
        // Exponential: 100 * 2^(attempt-1)
        // Attempt 1: 100ms
        // Attempt 2: 200ms
        // Attempt 3: 400ms
        return $this->initialBackoffMs * (1 << ($attempt - 1));
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
}
