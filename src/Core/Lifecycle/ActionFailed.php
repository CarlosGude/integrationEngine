<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Lifecycle;

use IntegrationEngine\Core\Contract\Action\AbstractAction;

/**
 * Fired when the HTTP call or mapping fails.
 * Includes the exception and duration.
 */
final readonly class ActionFailed implements IntegrationEngineEvent
{
    public function __construct(
        private AbstractAction $action,
        private string $integrationName,
        private float $timestamp,
        private \Throwable $error,
        private float $durationMs,
    ) {}

    public function action(): AbstractAction
    {
        return $this->action;
    }

    public function integrationName(): string
    {
        return $this->integrationName;
    }

    public function timestamp(): float
    {
        return $this->timestamp;
    }

    public function error(): \Throwable
    {
        return $this->error;
    }

    public function durationMs(): float
    {
        return $this->durationMs;
    }
}
