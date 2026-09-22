<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core\Event;

use IntegrationEngine\Core\Batch\EngineRequest;
use IntegrationEngine\Core\Event\RequestFailed;
use IntegrationEngine\Core\Event\RequestSent;
use IntegrationEngine\Core\Event\ResponseMapped;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Tests\Fake\FakeCache;
use IntegrationEngine\Tests\Fake\FakeClient;
use IntegrationEngine\Tests\Fake\FakeConfigPort;
use IntegrationEngine\Tests\Fake\FakeEventDispatcher;
use IntegrationEngine\Tests\Fake\FakeTokenAction;
use PHPUnit\Framework\TestCase;

final class SafeLifecycleEventsTest extends TestCase
{
    public function testSuccessEmitsOnlySafeMetadataInOrder(): void
    {
        $dispatcher = new FakeEventDispatcher();
        $client = new FakeClient();
        $client->setResponse(FakeTokenAction::getName(), ['secret' => 'CONFIDENTIAL_789']);
        $this->engine($client, $dispatcher)->send(FakeTokenAction::getName());
        self::assertSame([RequestSent::class, ResponseMapped::class], array_map(static fn (object $e): string => $e::class, $dispatcher->events));
        self::assertStringNotContainsString('CONFIDENTIAL_789', var_export($dispatcher->events, true));
        self::assertInstanceOf(RequestSent::class, $dispatcher->events[0]);
        self::assertSame('/token/{id}', $dispatcher->events[0]->path);
        self::assertInstanceOf(ResponseMapped::class, $dispatcher->events[1]);
        self::assertGreaterThanOrEqual(0, $dispatcher->events[1]->durationMs);
    }

    public function testFailurePreservesExceptionButNeverPublishesItsMessage(): void
    {
        $dispatcher = new FakeEventDispatcher();
        $client = new FakeClient();
        $failure = new \RuntimeException('Bearer SECRET_TOKEN_123 CONFIDENTIAL_789');
        $client->queueException(FakeTokenAction::getName(), $failure);

        try {
            $this->engine($client, $dispatcher)->send(FakeTokenAction::getName());
            self::fail('Expected request failure.');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
        self::assertSame([RequestSent::class, RequestFailed::class], array_map(static fn (object $e): string => $e::class, $dispatcher->events));
        self::assertStringNotContainsString('SECRET_TOKEN_123', var_export($dispatcher->events, true));
        self::assertStringNotContainsString('CONFIDENTIAL_789', var_export($dispatcher->events, true));
    }

    public function testBatchEmitsOnePairPerKeyIncludingPreparationFailure(): void
    {
        $dispatcher = new FakeEventDispatcher();
        $results = $this->engine(new FakeClient(), $dispatcher)->sendMany([
            'ok' => new EngineRequest(FakeTokenAction::getName()),
            'bad' => new EngineRequest('missing'),
        ]);
        self::assertCount(2, $results);
        self::assertCount(4, $dispatcher->events);
        $keys = array_map(static fn (object $e): mixed => property_exists($e, 'requestKey') ? $e->requestKey : null, $dispatcher->events);
        self::assertSame(['ok', 'bad', 'ok', 'bad'], $keys);
        self::assertInstanceOf(ResponseMapped::class, $dispatcher->events[2]);
        self::assertInstanceOf(RequestFailed::class, $dispatcher->events[3]);
    }

    private function engine(FakeClient $client, FakeEventDispatcher $dispatcher): IntegrationEngine
    {
        $config = new FakeConfigPort();
        $config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token/{id}'));

        return new IntegrationEngine($config, $client, new FakeCache(), 'shop', eventDispatcher: $dispatcher);
    }
}
