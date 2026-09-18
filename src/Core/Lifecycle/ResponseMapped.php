<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Lifecycle;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;

/**
 * Fired after response is mapped to a DTO, before ActionCompleted.
 * Use this to track mapping duration separately from HTTP call.
 */
final readonly class ResponseMapped implements IntegrationEngineEvent
{
    public function __construct(
        private AbstractAction $action,
        private string $integrationName,
        private float $timestamp,
        private ResponseInterface $response,
        private float $httpDurationMs,
        private float $mappingDurationMs,
        private float $totalDurationMs,
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

    public function httpDurationMs(): float
    {
        return $this->httpDurationMs;
    }

    public function mappingDurationMs(): float
    {
        return $this->mappingDurationMs;
    }

    public function totalDurationMs(): float
    {
        return $this->totalDurationMs;
    }
}
