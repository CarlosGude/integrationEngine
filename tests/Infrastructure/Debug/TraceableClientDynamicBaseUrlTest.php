<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Debug;

use IntegrationEngine\Core\Batch\EngineRequest;
use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Client\BatchClientInterface;
use IntegrationEngine\Core\Contract\Client\ClientInterface;
use IntegrationEngine\Core\Contract\Client\DynamicBaseUrlClientInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Infrastructure\Debug\IntegrationEngineDataCollector;
use IntegrationEngine\Infrastructure\Debug\TraceableBatchClient;
use IntegrationEngine\Infrastructure\Debug\TraceableClient;
use IntegrationEngine\Tests\Fake\FakeCache;
use IntegrationEngine\Tests\Fake\FakeClient;
use IntegrationEngine\Tests\Fake\FakeConfigPort;
use IntegrationEngine\Tests\Fake\FakePathAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression for the bug where TraceableClient/TraceableBatchClient (wired
 * by IntegrationCompilerPass whenever kernel.debug = true) did not implement
 * DynamicBaseUrlClientInterface, so IntegrationEngine::resolveClient()'s
 * instanceof check silently ignored any explicit baseUrl in dev/test.
 */
final class TraceableClientDynamicBaseUrlTest extends TestCase
{
    #[Test]
    public function traceableClientDelegatesWithBaseUrlToADecoratedClientThatSupportsIt(): void
    {
        $inner = new FakeClient();
        $collector = new IntegrationEngineDataCollector();
        $traceable = new TraceableClient($inner, 'my_api', $collector);

        self::assertInstanceOf(DynamicBaseUrlClientInterface::class, $traceable);

        $resolved = $traceable->withBaseUrl('https://tenant-one.example.com');

        // The original instance is untouched.
        self::assertInstanceOf(TraceableClient::class, $traceable);
        self::assertNull($inner->lastBaseUrl());

        $resolved->send(FakePathAction::create('GET', '/items'));

        self::assertSame('https://tenant-one.example.com', $inner->lastBaseUrl());
    }

    #[Test]
    public function traceableBatchClientDelegatesWithBaseUrlPreservingBatchCapability(): void
    {
        $inner = new FakeBatchAndDynamicBaseUrlClient();
        $collector = new IntegrationEngineDataCollector();
        $traceable = new TraceableBatchClient($inner, 'my_api', $collector);

        $resolved = $traceable->withBaseUrl('https://tenant-two.example.com');

        self::assertInstanceOf(BatchClientInterface::class, $resolved);
        self::assertInstanceOf(TraceableBatchClient::class, $resolved);

        $resolved->send(FakePathAction::create('GET', '/items'));

        self::assertSame('https://tenant-two.example.com', $inner->lastBaseUrl());
    }

    #[Test]
    public function withBaseUrlIsANoOpWhenTheDecoratedClientDoesNotSupportIt(): void
    {
        $inner = new FakeStaticOnlyClient();
        $collector = new IntegrationEngineDataCollector();
        $traceable = new TraceableClient($inner, 'my_api', $collector);

        $resolved = $traceable->withBaseUrl('https://tenant-one.example.com');

        self::assertSame($traceable, $resolved);

        $resolved->send(FakePathAction::create('GET', '/items'));

        self::assertSame(1, $inner->callCount());
    }

    /**
     * End-to-end: a real IntegrationEngine wired with TraceableClient (the
     * exact shape IntegrationCompilerPass produces when kernel.debug is
     * true) must still honor a per-request baseUrl — this is the scenario
     * that silently broke before the fix.
     */
    #[Test]
    public function engineWiredWithTraceableClientStillHonorsPerRequestBaseUrl(): void
    {
        $config = new FakeConfigPort();
        $config->register(FakePathAction::getName(), FakePathAction::create('GET', '/items'));

        $client = new FakeClient();
        $client->setResponse(FakePathAction::getName(), []);

        $traceable = new TraceableClient($client, 'my_api', new IntegrationEngineDataCollector());

        $engine = new IntegrationEngine(
            config: $config,
            client: $traceable,
            cache: new FakeCache(),
            integrationName: 'my_api',
        );

        $engine->send(FakePathAction::getName(), baseUrl: 'https://tenant-a.example.com');
        self::assertSame('https://tenant-a.example.com', $client->lastBaseUrl());

        $engine->send(FakePathAction::getName(), baseUrl: 'https://tenant-b.example.com');
        self::assertSame('https://tenant-b.example.com', $client->lastBaseUrl());
    }

    #[Test]
    public function engineWiredWithTraceableBatchClientStillHonorsPerRequestBaseUrlInABatch(): void
    {
        $config = new FakeConfigPort();
        $config->register(FakePathAction::getName(), FakePathAction::create('GET', '/items'));

        $client = new FakeClient();
        $client->setResponse(FakePathAction::getName(), []);

        $batchClient = new FakeBatchAndDynamicBaseUrlClient($client);
        $traceable = new TraceableBatchClient($batchClient, 'my_api', new IntegrationEngineDataCollector());

        $engine = new IntegrationEngine(
            config: $config,
            client: $traceable,
            cache: new FakeCache(),
            integrationName: 'my_api',
        );

        $results = $engine->sendMany([
            'a' => EngineRequest::create(FakePathAction::getName(), baseUrl: 'https://tenant-a.example.com'),
            'b' => EngineRequest::create(FakePathAction::getName(), baseUrl: 'https://tenant-b.example.com'),
        ]);

        self::assertTrue($results['a']->isSuccess());
        self::assertTrue($results['b']->isSuccess());
    }
}

// ── Fixtures ──────────────────────────────────────────────────────────────────

/**
 * Implements BatchClientInterface & ClientInterface & DynamicBaseUrlClientInterface
 * at once, since TraceableBatchClient requires its decorated client to be
 * BatchClientInterface&ClientInterface.
 */
final class FakeBatchAndDynamicBaseUrlClient implements BatchClientInterface, ClientInterface, DynamicBaseUrlClientInterface
{
    public function __construct(
        private readonly FakeClient $inner = new FakeClient(),
        private readonly ?string $baseUrl = null,
    ) {}

    public function withBaseUrl(string $baseUrl): static
    {
        return new self($this->inner->withBaseUrl($baseUrl), $baseUrl);
    }

    public function lastBaseUrl(): ?string
    {
        return $this->inner->lastBaseUrl();
    }

    /** @return array<mixed> */
    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
        $client = null !== $this->baseUrl ? $this->inner->withBaseUrl($this->baseUrl) : $this->inner;

        return $client->send($action, $context, $headers);
    }

    /**
     * @param array<array-key, PreparedRequest> $requests
     *
     * @return array<array-key, array<mixed>|\Throwable>
     */
    public function sendMany(array $requests): array
    {
        // IntegrationEngine::dispatchBatch() already groups requests by
        // baseUrl and resolves withBaseUrl() once per group before calling
        // sendMany() — every request here shares $this->baseUrl.
        $client = null !== $this->baseUrl ? $this->inner->withBaseUrl($this->baseUrl) : $this->inner;
        $results = [];

        foreach ($requests as $key => $request) {
            try {
                $results[$key] = $client->send($request->action, $request->context, $request->headers);
            } catch (\Throwable $e) {
                $results[$key] = $e;
            }
        }

        return $results;
    }
}

/**
 * Implements only ClientInterface — proves withBaseUrl() is a no-op rather
 * than breaking when the decorated client doesn't support dynamic base URLs.
 */
final class FakeStaticOnlyClient implements ClientInterface
{
    private int $callCount = 0;

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
        ++$this->callCount;

        return [];
    }
}
