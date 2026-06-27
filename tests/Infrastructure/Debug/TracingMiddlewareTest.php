<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Debug;

use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Infrastructure\Debug\IntegrationEngineDataCollector;
use IntegrationEngine\Infrastructure\Debug\TracingMiddleware;
use IntegrationEngine\Tests\Fake\FakePathAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TracingMiddlewareTest extends TestCase
{
    // ── process() ─────────────────────────────────────────────────────────────

    #[Test]
    public function processRecordsCallWithPositiveDuration(): void
    {
        $collector = new IntegrationEngineDataCollector();
        $mw = new TracingMiddleware('my_api', $collector);
        $next = static fn (): array => ['ok' => true];

        $result = $mw->process(FakePathAction::create('GET', '/items'), null, null, $next);

        self::assertSame(['ok' => true], $result);
        self::assertSame(1, $collector->getTotalCalls());
        $call = $collector->getCalls()[0];
        self::assertSame('my_api', $call->integrationName);
        self::assertSame(FakePathAction::getName(), $call->actionName);
        self::assertGreaterThanOrEqual(0.0, $call->durationMs);
        self::assertNull($call->error);
    }

    #[Test]
    public function processRethrowsAndRecordsErrorWithStatusCode(): void
    {
        $collector = new IntegrationEngineDataCollector();
        $mw = new TracingMiddleware('my_api', $collector);
        $next = static fn (): never => throw new RequestResponseException(503, 'down');

        try {
            $mw->process(FakePathAction::create('GET', '/items'), null, null, $next);
            self::fail('Expected exception to propagate.');
        } catch (RequestResponseException $e) {
            self::assertSame(503, $e->statusCode);
        }

        self::assertSame(1, $collector->getErrorCount());
        self::assertSame(503, $collector->getCalls()[0]->statusCode);
    }

    // ── processMany() ─────────────────────────────────────────────────────────

    #[Test]
    public function processManyRecordsOneCallPerRequest(): void
    {
        $collector = new IntegrationEngineDataCollector();
        $mw = new TracingMiddleware('my_api', $collector);

        $mw->processMany(
            [
                'a' => new PreparedRequest(FakePathAction::create('GET', '/a'), null, null),
                'b' => new PreparedRequest(FakePathAction::create('GET', '/b'), null, null),
            ],
            static fn (array $reqs): array => ['a' => [], 'b' => []],
        );

        self::assertSame(2, $collector->getTotalCalls());
        self::assertSame('my_api', $collector->getCalls()[0]->integrationName);
    }

    #[Test]
    public function processManyRecordsThrowableResultAsError(): void
    {
        $collector = new IntegrationEngineDataCollector();
        $mw = new TracingMiddleware('my_api', $collector);

        $mw->processMany(
            ['a' => new PreparedRequest(FakePathAction::create('GET', '/items'), null, null)],
            static fn (array $reqs): array => ['a' => new RequestResponseException(500, 'boom')],
        );

        self::assertSame(1, $collector->getErrorCount());
        self::assertSame(500, $collector->getCalls()[0]->statusCode);
    }
}
