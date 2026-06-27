<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Debug;

use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Infrastructure\Debug\IntegrationEngineDataCollector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class IntegrationEngineDataCollectorTest extends TestCase
{
    #[Test]
    public function recordCallAccumulatesEntriesExposedByGetCalls(): void
    {
        $collector = new IntegrationEngineDataCollector();

        $collector->recordCall('my_api', 'GetItem', 'GET', '/items/{id}', 12.5, null);
        $collector->recordCall('my_api', 'GetItems', 'GET', '/items', 7.0, null);

        self::assertCount(2, $collector->getCalls());
        self::assertSame(2, $collector->getTotalCalls());
        self::assertSame('GetItem', $collector->getCalls()[0]->actionName);
    }

    #[Test]
    public function getTotalDurationMsSumsEveryRecordedCall(): void
    {
        $collector = new IntegrationEngineDataCollector();

        $collector->recordCall('my_api', 'A', 'GET', '/a', 10.0, null);
        $collector->recordCall('my_api', 'B', 'GET', '/b', 15.5, null);

        self::assertSame(25.5, $collector->getTotalDurationMs());
    }

    #[Test]
    public function getErrorCountCountsOnlyFailedCalls(): void
    {
        $collector = new IntegrationEngineDataCollector();

        $collector->recordCall('my_api', 'A', 'GET', '/a', 1.0, null);
        $collector->recordCall('my_api', 'B', 'GET', '/b', 1.0, new RequestResponseException(500, 'boom'), 500);
        $collector->recordCall('my_api', 'C', 'GET', '/c', 1.0, new \RuntimeException('network error'));

        self::assertSame(2, $collector->getErrorCount());
        self::assertSame(500, $collector->getCalls()[1]->statusCode);
        self::assertNull($collector->getCalls()[2]->statusCode);
    }

    #[Test]
    public function getCachedCountCountsOnlyCachedCalls(): void
    {
        $collector = new IntegrationEngineDataCollector();

        $collector->recordCall('my_api', 'A', 'GET', '/a', 0.0, null, cached: true);
        $collector->recordCall('my_api', 'B', 'GET', '/b', 10.0, null);
        $collector->recordCall('my_api', 'C', 'GET', '/c', 0.0, null, cached: true);

        self::assertSame(2, $collector->getCachedCount());
        self::assertTrue($collector->getCalls()[0]->cached);
        self::assertFalse($collector->getCalls()[1]->cached);
    }

    #[Test]
    public function resetClearsAccumulatedState(): void
    {
        $collector = new IntegrationEngineDataCollector();
        $collector->recordCall('my_api', 'A', 'GET', '/a', 1.0, null);

        $collector->reset();

        self::assertSame(0, $collector->getTotalCalls());
        self::assertSame(0.0, $collector->getTotalDurationMs());
    }

    #[Test]
    public function getNameReturnsTheCollectorId(): void
    {
        self::assertSame('integration_engine', (new IntegrationEngineDataCollector())->getName());
    }

    #[Test]
    public function collectIsANoOpSinceCallsAreRecordedEagerly(): void
    {
        $collector = new IntegrationEngineDataCollector();
        $collector->recordCall('my_api', 'A', 'GET', '/a', 1.0, null);

        $collector->collect(Request::create('/'), new Response());

        self::assertSame(1, $collector->getTotalCalls());
    }
}
