<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Client\ClientInterface;
use IntegrationEngine\Core\Contract\Client\DynamicBaseUrlClientInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;

final class FakeClient implements ClientInterface, DynamicBaseUrlClientInterface
{
    /**
     * Shared with every instance returned by withBaseUrl(), so recordings
     * made on a resolved-per-request clone stay visible through the
     * original instance the test holds onto.
     */
    private readonly FakeClientState $state;

    public function __construct(?FakeClientState $state = null, private readonly ?string $baseUrl = null)
    {
        $this->state = $state ?? new FakeClientState();
    }

    public function withBaseUrl(string $baseUrl): static
    {
        return new self($this->state, $baseUrl);
    }

    public function baseUrl(): ?string
    {
        return $this->baseUrl;
    }

    /**
     * @param array<mixed>                $response
     * @param array<string, list<string>> $headers
     */
    public function setResponse(string $name, array $response, array $headers = []): void
    {
        $this->state->responses[$name] = $response;
        $this->state->responseHeaders[$name] = $headers;
    }

    /**
     * Queues an exception thrown on the next send() for the action.
     * Each queued exception is consumed by one call; once the queue is
     * empty the configured response is returned again.
     */
    public function queueException(string $name, \Throwable $exception): void
    {
        $this->state->pendingExceptions[$name][] = $exception;
    }

    public function callCount(string $name): int
    {
        return $this->state->callCount[$name] ?? 0;
    }

    public function lastAction(): ?AbstractAction
    {
        return $this->state->lastAction;
    }

    public function lastContext(): ?ActionContextInterface
    {
        return $this->state->lastContext;
    }

    public function lastBaseUrl(): ?string
    {
        return $this->state->lastBaseUrl;
    }

    /** The baseUrl the client instance had when it last sent the given action — unlike lastBaseUrl(), unaffected by later sends of other actions. */
    public function baseUrlUsedFor(string $actionName): ?string
    {
        return $this->state->baseUrlByAction[$actionName] ?? null;
    }

    /** @return array{body: array<mixed>, headers: array<string, list<string>>} */
    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
        $this->state->lastAction = $action;
        $this->state->lastContext = $context;
        $this->state->lastBaseUrl = $this->baseUrl;
        $this->state->baseUrlByAction[$action::getName()] = $this->baseUrl;
        $this->state->callCount[$action::getName()] = ($this->state->callCount[$action::getName()] ?? 0) + 1;

        if (!empty($this->state->pendingExceptions[$action::getName()])) {
            throw array_shift($this->state->pendingExceptions[$action::getName()]);
        }

        return [
            'body' => $this->state->responses[$action::getName()] ?? [],
            'headers' => $this->state->responseHeaders[$action::getName()] ?? [],
        ];
    }
}

/**
 * Mutable recording shared across a FakeClient and every clone produced
 * by withBaseUrl(), so a test can assert on the original instance.
 */
final class FakeClientState
{
    /** @var array<string, array<mixed>> */
    public array $responses = [];

    /** @var array<string, array<string, list<string>>> */
    public array $responseHeaders = [];

    /** @var array<string, int> */
    public array $callCount = [];

    /** @var array<string, list<\Throwable>> */
    public array $pendingExceptions = [];
    public ?AbstractAction $lastAction = null;
    public ?ActionContextInterface $lastContext = null;
    public ?string $lastBaseUrl = null;

    /** @var array<string, ?string> */
    public array $baseUrlByAction = [];
}
