<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Batch\EngineRequest;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Client\ClientInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Tests\Fake\FakePathAction;
use PHPUnit\Framework\Attributes\Test;

final class EngineDynamicBaseUrlTest extends IntegrationEngineTestCase
{
    #[Test]
    public function sendWithBaseUrlUsesItWhenClientSupportsDynamicBaseUrl(): void
    {
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/items'));
        $this->client->setResponse(FakePathAction::getName(), []);

        $this->engine->send(FakePathAction::getName(), baseUrl: 'https://tenant-one.example.com');

        self::assertSame('https://tenant-one.example.com', $this->client->lastBaseUrl());
    }

    #[Test]
    public function sendWithoutBaseUrlBehavesExactlyAsBefore(): void
    {
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/items'));
        $this->client->setResponse(FakePathAction::getName(), []);

        $this->engine->send(FakePathAction::getName());

        self::assertNull($this->client->lastBaseUrl());
    }

    #[Test]
    public function sendWithBaseUrlIsIgnoredSilentlyWhenClientDoesNotSupportIt(): void
    {
        $config = $this->config;
        $config->register(FakePathAction::getName(), FakePathAction::create('GET', '/items'));

        $client = new EngStaticOnlyClient();
        $client->setResponse(FakePathAction::getName(), []);

        $engine = new IntegrationEngine(
            config: $config,
            client: $client,
            cache: $this->cache,
            integrationName: 'test_integration',
        );

        $response = $engine->send(FakePathAction::getName(), baseUrl: 'https://tenant-one.example.com');

        self::assertNotNull($client->lastAction());
        self::assertSame(1, $client->callCount());
        self::assertSame([], $response->toArray());
    }

    #[Test]
    public function sendManyHitsEachRequestsOwnBaseUrl(): void
    {
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/items'));
        $this->client->setResponse(FakePathAction::getName(), []);

        $results = $this->engine->sendMany([
            'a' => EngineRequest::create(FakePathAction::getName(), baseUrl: 'https://tenant-a.example.com'),
            'b' => EngineRequest::create(FakePathAction::getName(), baseUrl: 'https://tenant-b.example.com'),
        ]);

        self::assertTrue($results['a']->isSuccess());
        self::assertTrue($results['b']->isSuccess());
        // FakeClient's state is shared across withBaseUrl() clones, so the
        // last recorded baseUrl reflects whichever request ran last in the
        // sequential per-group dispatch.
        self::assertSame('https://tenant-b.example.com', $this->client->lastBaseUrl());
    }
}

// ── Fixtures ──────────────────────────────────────────────────────────────────

/**
 * A ClientInterface that does NOT implement DynamicBaseUrlClientInterface —
 * proves an explicit baseUrl is ignored rather than breaking the call.
 */
final class EngStaticOnlyClient implements ClientInterface
{
    private ?AbstractAction $lastAction = null;
    private int $callCount = 0;

    /** @var array<string, array<mixed>> */
    private array $responses = [];

    /** @param array<mixed> $response */
    public function setResponse(string $name, array $response): void
    {
        $this->responses[$name] = $response;
    }

    public function lastAction(): ?AbstractAction
    {
        return $this->lastAction;
    }

    public function callCount(): int
    {
        return $this->callCount;
    }

    /** @return array<mixed> */
    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
        $this->lastAction = $action;
        ++$this->callCount;

        return $this->responses[$action::getName()] ?? [];
    }
}
