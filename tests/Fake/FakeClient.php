<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\ActionContextInterface;
use IntegrationEngine\Core\Contract\ClientInterface;
use IntegrationEngine\Core\Contract\RequestHeadersInterface;

final class FakeClient implements ClientInterface
{
    /** @var array<string, array<mixed>> */
    private array $responses = [];

    /** @var array<string, int> */
    private array $callCount = [];

    /** @var array<string, list<\Throwable>> */
    private array $pendingExceptions = [];
    private ?AbstractAction $lastAction = null;
    private ?ActionContextInterface $lastContext = null;

    /** @param array<mixed> $response */
    public function setResponse(string $name, array $response): void
    {
        $this->responses[$name] = $response;
    }

    /**
     * Queues an exception thrown on the next send() for the action.
     * Each queued exception is consumed by one call; once the queue is
     * empty the configured response is returned again.
     */
    public function queueException(string $name, \Throwable $exception): void
    {
        $this->pendingExceptions[$name][] = $exception;
    }

    public function callCount(string $name): int
    {
        return $this->callCount[$name] ?? 0;
    }

    public function lastAction(): ?AbstractAction
    {
        return $this->lastAction;
    }

    public function lastContext(): ?ActionContextInterface
    {
        return $this->lastContext;
    }

    /** @return array<mixed> */
    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
        $this->lastAction = $action;
        $this->lastContext = $context;
        $this->callCount[$action::getName()] = ($this->callCount[$action::getName()] ?? 0) + 1;

        if (!empty($this->pendingExceptions[$action::getName()])) {
            throw array_shift($this->pendingExceptions[$action::getName()]);
        }

        return $this->responses[$action::getName()] ?? [];
    }
}
