<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Debug;

use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Infrastructure\Debug\IntegrationEngineDataCollector;
use IntegrationEngine\Infrastructure\Debug\TraceableClient;
use IntegrationEngine\Tests\Fake\FakeClient;
use IntegrationEngine\Tests\Fake\FakePathAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TraceableClientTest extends TestCase
{
    #[Test]
    public function delegatesToDecoratedClientAndReturnsItsResult(): void
    {
        $inner = new FakeClient();
        $inner->setResponse(FakePathAction::getName(), ['ok' => true]);
        $collector = new IntegrationEngineDataCollector();
        $traceable = new TraceableClient($inner, 'my_api', $collector);

        $result = $traceable->send(FakePathAction::create('GET', '/items'));

        self::assertSame(['ok' => true], $result);
        self::assertSame(1, $inner->callCount(FakePathAction::getName()));
    }

    #[Test]
    public function recordsACallWithAPositiveDuration(): void
    {
        $inner = new FakeClient();
        $inner->setResponse(FakePathAction::getName(), []);
        $collector = new IntegrationEngineDataCollector();
        $traceable = new TraceableClient($inner, 'my_api', $collector);

        $traceable->send(FakePathAction::create('GET', '/items'));

        self::assertSame(1, $collector->getTotalCalls());
        $call = $collector->getCalls()[0];
        self::assertSame('my_api', $call->integrationName);
        self::assertSame(FakePathAction::getName(), $call->actionName);
        self::assertSame('GET', $call->method);
        self::assertSame('/items', $call->path);
        self::assertGreaterThanOrEqual(0.0, $call->durationMs);
        self::assertNull($call->error);
    }

    #[Test]
    public function rethrowsTheOriginalExceptionAndStillRecordsTheCall(): void
    {
        $inner = new FakeClient();
        $inner->queueException(FakePathAction::getName(), new RequestResponseException(500, 'boom'));
        $collector = new IntegrationEngineDataCollector();
        $traceable = new TraceableClient($inner, 'my_api', $collector);

        try {
            $traceable->send(FakePathAction::create('GET', '/items'));
            self::fail('Expected RequestResponseException to propagate.');
        } catch (RequestResponseException $e) {
            self::assertSame(500, $e->statusCode);
        }

        self::assertSame(1, $collector->getTotalCalls());
        $call = $collector->getCalls()[0];
        self::assertNotNull($call->error);
        self::assertSame(500, $call->statusCode);
        self::assertSame(1, $collector->getErrorCount());
    }
}
