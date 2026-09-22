<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Resilience;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Infrastructure\Resilience\SymfonyErrorClassifier;

/**
 * Legacy API preserving the original Symfony-aware default.
 *
 * @deprecated use ExponentialBackoff with an explicit SymfonyErrorClassifier when needed
 */
final class ExponentialBackoffPolicy implements ResiliencePolicyInterface
{
    private readonly ExponentialBackoff $policy;

    public function __construct(int $maxAttempts = 3, int $initialBackoffMs = 100)
    {
        $this->policy = new ExponentialBackoff($maxAttempts, $initialBackoffMs, new SymfonyErrorClassifier());
    }

    public function shouldRetry(\Throwable $e, int $attempt): bool
    {
        return $this->policy->shouldRetry($e, $attempt);
    }

    public function getBackoffMs(int $attempt): int
    {
        return $this->policy->getBackoffMs($attempt);
    }

    public function getMaxAttempts(): int
    {
        return $this->policy->getMaxAttempts();
    }

    public function getFallback(AbstractAction $action, \Throwable $e): mixed
    {
        return $this->policy->getFallback($action, $e);
    }

    public function getName(): string
    {
        return $this->policy->getName();
    }
}
