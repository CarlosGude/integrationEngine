<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Lifecycle;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;

/**
 * Fired after successful HTTP call and mapping.
 * Includes the mapped response DTO and duration.
 */
final readonly class ActionCompleted implements IntegrationEngineEvent
{
    public function __construct(
        private AbstractAction $action,
        private string $integrationName,
        private float $timestamp,
        private ResponseInterface $response,
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

    public function response(): ResponseInterface
    {
        return $this->response;
    }

    public function durationMs(): float
    {
        return $this->durationMs;
    }
}
