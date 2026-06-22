<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Debug;

use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Infrastructure\Debug\IntegrationEngineDataCollector;
use IntegrationEngine\Infrastructure\Debug\TraceableBatchClient;
use IntegrationEngine\Tests\Fake\FakeBatchClient;
use IntegrationEngine\Tests\Fake\FakePathAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TraceableBatchClientTest extends TestCase
{
    #[Test]
    public function delegatesSendManyAndReturnsTheSameResults(): void
    {
        $inner = new FakeBatchClient();
        $inner->inner()->setResponse(FakePathAction::getName(), ['ok' => true]);
        $collector = new IntegrationEngineDataCollector();
        $traceable = new TraceableBatchClient($inner, 'my_api', $collector);

        $results = $traceable->sendMany([
            'a' => new PreparedRequest(FakePathAction::create('GET', '/items'), null, null),
        ]);

        self::assertSame(['ok' => true], $results['a']);
        self::assertSame(1, $inner->batchCount());
    }

    #[Test]
    public function recordsOneCallPerRequestInTheBatch(): void
    {
        $inner = new FakeBatchClient();
        $inner->inner()->setResponse(FakePathAction::getName(), []);
        $collector = new IntegrationEngineDataCollector();
        $traceable = new TraceableBatchClient($inner, 'my_api', $collector);

        $traceable->sendMany([
            'a' => new PreparedRequest(FakePathAction::create('GET', '/items'), null, null),
            'b' => new PreparedRequest(FakePathAction::create('GET', '/other'), null, null),
        ]);

        self::assertSame(2, $collector->getTotalCalls());
        self::assertSame('my_api', $collector->getCalls()[0]->integrationName);
    }

    #[Test]
    public function recordsAPerKeyErrorWithoutAbortingTheBatch(): void
    {
        $inner = new FakeBatchClient();
        $inner->inner()->queueException(FakePathAction::getName(), new RequestResponseException(500, 'boom'));
        $collector = new IntegrationEngineDataCollector();
        $traceable = new TraceableBatchClient($inner, 'my_api', $collector);

        $results = $traceable->sendMany([
            'broken' => new PreparedRequest(FakePathAction::create('GET', '/broken'), null, null),
        ]);

        self::assertInstanceOf(RequestResponseException::class, $results['broken']);
        self::assertSame(1, $collector->getErrorCount());
        self::assertSame(500, $collector->getCalls()[0]->statusCode);
    }
}
