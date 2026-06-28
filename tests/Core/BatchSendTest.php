<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Batch\BatchResultCollection;
use IntegrationEngine\Core\Batch\EngineRequest;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Core\Response\EmptyResponse;
use IntegrationEngine\Tests\Fake\FakeBatchClient;
use IntegrationEngine\Tests\Fake\FakePathAction;
use IntegrationEngine\Tests\Fake\FakeTokenAction;
use IntegrationEngine\Tests\Fake\FakeTokenResponse;
use PHPUnit\Framework\Attributes\Test;

final class BatchSendTest extends IntegrationEngineTestCase
{
    // ── sendMany: keys and mapping ────────────────────────────────────────────

    #[Test]
    public function sendManyPreservesKeysAndMapsEachActionWithItsOwnMapper(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/orders'));
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'tok']);
        $this->client->setResponse(FakePathAction::getName(), []);

        $results = $this->engine->sendMany([
            'token' => new EngineRequest(FakeTokenAction::getName()),
            'path' => new EngineRequest(FakePathAction::getName()),
        ]);

        self::assertSame(['token', 'path'], $results->keys());
        self::assertTrue($results['token']->isSuccess());
        self::assertTrue($results['path']->isSuccess());
        self::assertInstanceOf(FakeTokenResponse::class, $results['token']->response());
        self::assertSame(['access_token' => 'tok'], $results['token']->response()->toArray());
        self::assertInstanceOf(EmptyResponse::class, $results['path']->response());
    }

    #[Test]
    public function sendManyStoresActionClassPerItemInCollection(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/orders'));
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'tok']);
        $this->client->setResponse(FakePathAction::getName(), []);

        $results = $this->engine->sendMany([
            'token' => new EngineRequest(FakeTokenAction::getName()),
            'path' => new EngineRequest(FakePathAction::getName()),
        ]);

        self::assertSame(FakeTokenAction::class, $results->actionClassFor('token'));
        self::assertSame(FakePathAction::class, $results->actionClassFor('path'));
    }

    #[Test]
    public function sendManyWithEmptyArrayReturnsEmptyCollectionWithoutTouchingClient(): void
    {
        $empty = $this->engine->sendMany([]);

        self::assertInstanceOf(BatchResultCollection::class, $empty);
        self::assertCount(0, $empty);
        self::assertNull($this->client->lastAction());
    }

    #[Test]
    public function sendManyWithEmptyArrayDoesNotInvokeABatchClient(): void
    {
        $batchClient = new FakeBatchClient();
        $engine = new IntegrationEngine(
            config: $this->config,
            client: $batchClient,
            cache: $this->cache,
            integrationName: 'test_integration',
        );

        $empty = $engine->sendMany([]);

        self::assertInstanceOf(BatchResultCollection::class, $empty);
        self::assertCount(0, $empty);
        self::assertSame(0, $batchClient->batchCount());
    }

    // ── sendManyOrFail ────────────────────────────────────────────────────────

    #[Test]
    public function sendManyOrFailReturnsMappedResponsesPreservingKeys(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/orders'));
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'tok']);
        $this->client->setResponse(FakePathAction::getName(), []);

        $responses = $this->engine->sendManyOrFail([
            'token' => new EngineRequest(FakeTokenAction::getName()),
            'path' => new EngineRequest(FakePathAction::getName()),
        ]);

        self::assertSame(['token', 'path'], array_keys($responses));
        self::assertInstanceOf(FakeTokenResponse::class, $responses['token']);
        self::assertInstanceOf(EmptyResponse::class, $responses['path']);
    }

    // ── sendMany: batch-capable client ───────────────────────────────────────

    #[Test]
    public function sendManyRoutesThroughBatchClientWhenAvailable(): void
    {
        $batchClient = new FakeBatchClient();
        $engine = new IntegrationEngine(
            config: $this->config,
            client: $batchClient,
            cache: $this->cache,
            integrationName: 'test_integration',
        );
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/orders'));
        $batchClient->inner()->setResponse(FakePathAction::getName(), []);

        $results = $engine->sendMany([
            'a' => new EngineRequest(FakePathAction::getName()),
            'b' => new EngineRequest(FakePathAction::getName()),
        ]);

        self::assertSame(1, $batchClient->batchCount());
        self::assertSame(['a', 'b'], array_keys($batchClient->batches()[0]));
        self::assertTrue($results['a']->isSuccess());
        self::assertTrue($results['b']->isSuccess());
    }
}
