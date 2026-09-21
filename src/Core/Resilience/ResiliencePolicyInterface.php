<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Resilience;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use Throwable;

/**
 * Resilience policy for handling failures in API calls.
 *
 * Determines retry behavior, backoff strategy, and fallback values
 * for transient and permanent errors.
 */
interface ResiliencePolicyInterface
{
    /**
     * Should this error be retried?
     *
     * @return bool true if the error is transient and retryable
     */
    public function shouldRetry(Throwable $e, int $attempt): bool;

    /**
     * Calculate backoff time before retry N.
     *
     * Example: exponential backoff returns 100ms, 200ms, 400ms, 800ms...
     *
     * @param int $attempt the retry attempt number (1-indexed)
     * @return int milliseconds to wait before retry
     */
    public function getBackoffMs(int $attempt): int;

    /**
     * Maximum number of retry attempts before giving up.
     *
     * @return int number of retries (typically 3-5)
     */
    public function getMaxAttempts(): int;

    /**
     * Fallback value if all retries are exhausted.
     *
     * @param AbstractAction $action the action that failed
     * @param Throwable $e the final exception
     * @return mixed the fallback value (null, cached data, default, or throw)
     *
     * @throws Throwable if no fallback is available
     */
    public function getFallback(AbstractAction $action, Throwable $e): mixed;

    /**
     * Human-readable name for this policy.
     *
     * @return string e.g., "exponential_backoff", "linear_retry"
     */
    public function getName(): string;
}
