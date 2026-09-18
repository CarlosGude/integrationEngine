<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Lifecycle;

use IntegrationEngine\Core\Contract\Action\AbstractAction;

/**
 * Fired before the HTTP call.
 * Use this to log start, increment counters, or prepare context.
 */
final readonly class ActionStarted implements IntegrationEngineEvent
{
    public function __construct(
        private AbstractAction $action,
        private string $integrationName,
        private float $timestamp,
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
}
