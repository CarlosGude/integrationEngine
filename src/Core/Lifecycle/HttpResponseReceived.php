<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Lifecycle;

use IntegrationEngine\Core\Contract\Action\AbstractAction;

/**
 * Fired after HTTP response is received, before mapping.
 * Use this to track raw HTTP call duration.
 */
final readonly class HttpResponseReceived implements IntegrationEngineEvent
{
    public function __construct(
        private AbstractAction $action,
        private string $integrationName,
        private float $timestamp,
        private int $statusCode,
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

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function durationMs(): float
    {
        return $this->durationMs;
    }
}
