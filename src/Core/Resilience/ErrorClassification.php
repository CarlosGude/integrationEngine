<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Resilience;

final readonly class ErrorClassification
{
    public function __construct(public ?int $statusCode = null, public bool $networkError = false) {}

    public function isTransient(): bool
    {
        return $this->networkError || 408 === $this->statusCode || 429 === $this->statusCode
            || (null !== $this->statusCode && $this->statusCode >= 500 && $this->statusCode < 600);
    }

    public function isPermanent(): bool
    {
        return null !== $this->statusCode && $this->statusCode >= 400 && $this->statusCode < 500
            && 408 !== $this->statusCode && 429 !== $this->statusCode;
    }
}
