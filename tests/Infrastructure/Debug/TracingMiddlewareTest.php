<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Debug;

use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\Action\DefaultActionContext;
use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Infrastructure\Debug\IntegrationEngineDataCollector;
use IntegrationEngine\Infrastructure\Debug\TracingMiddleware;
use IntegrationEngine\Tests\Fake\FakeFormBody;
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
        // A fast in-process call must take well under a second — guards
        // against the elapsed-time subtraction being flipped into an
        // addition of two Unix timestamps (a multi-billion-ms "duration").
        self::assertLessThan(1000.0, $call->durationMs);
        self::assertNull($call->error);
    }

    #[Test]
    public function processRecordsNullBodyClassWhenActionHasNoBody(): void
    {
        $collector = new IntegrationEngineDataCollector();
        $mw = new TracingMiddleware('my_api', $collector);
        $next = static fn (): array => [];

        $mw->process(FakePathAction::create('GET', '/items'), null, null, $next);

        self::assertNull($collector->getCalls()[0]->bodyClass);
    }

    #[Test]
    public function processRecordsTheActionBodyClassWhenPresent(): void
    {
        $collector = new IntegrationEngineDataCollector();
        $mw = new TracingMiddleware('my_api', $collector);
        $next = static fn (): array => [];
        $body = FakeFormBody::create(['name' => 'Ada']);

        $mw->process(FakePathAction::create('POST', '/items', $body), null, null, $next);

        self::assertSame(FakeFormBody::class, $collector->getCalls()[0]->bodyClass);
    }

    /**
     * Pins down the elapsed-seconds-to-milliseconds conversion with a real,
     * measurable delay: an artificially slow call must be reported neither
     * as ~0 ms (elapsed divided by 1000 instead of multiplied) nor as an
     * astronomically large number (elapsed added to the start time instead
     * of subtracted).
     */
    #[Test]
    public function processReportsDurationInTheRightOrderOfMagnitude(): void
    {
        $collector = new IntegrationEngineDataCollector();
        $mw = new TracingMiddleware('my_api', $collector);
        $next = static function (): array {
            usleep(20_000);

            return [];
        };

        $mw->process(FakePathAction::create('GET', '/items'), null, null, $next);

        $durationMs = $collector->getCalls()[0]->durationMs;
        self::assertGreaterThan(10.0, $durationMs);
        self::assertLessThan(1000.0, $durationMs);
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

    // ── capturePayloads ──────────────────────────────────────────────────────

    #[Test]
    public function processDoesNotCapturePayloadsByDefault(): void
    {
        $collector = new IntegrationEngineDataCollector();
        $mw = new TracingMiddleware('my_api', $collector);
        $body = FakeFormBody::create(['name' => 'Ada']);
        $next = static fn (): array => ['body' => ['id' => 42], 'headers' => []];

        $mw->process(
            FakePathAction::create('POST', '/items', $body),
            DefaultActionContext::create(['id' => 1]),
            null,
            $next,
        );

        $call = $collector->getCalls()[0];
        self::assertNull($call->bodyData);
        self::assertNull($call->contextData);
        self::assertNull($call->responseData);
    }

    #[Test]
    public function processCapturesBodyContextAndResponseWhenEnabled(): void
    {
        $collector = new IntegrationEngineDataCollector();
        $mw = new TracingMiddleware('my_api', $collector, capturePayloads: true);
        $body = FakeFormBody::create(['name' => 'Ada']);
        $next = static fn (): array => ['body' => ['id' => 42], 'headers' => []];

        $mw->process(
            FakePathAction::create('POST', '/items', $body),
            DefaultActionContext::create(['id' => 1]),
            null,
            $next,
        );

        $call = $collector->getCalls()[0];
        self::assertSame(['name' => 'Ada'], $call->bodyData);
        self::assertSame(['id' => 1], $call->contextData);
        self::assertSame(['id' => 42], $call->responseData);
    }

    #[Test]
    public function processNeverCapturesResponseDataWhenTheCallFails(): void
    {
        $collector = new IntegrationEngineDataCollector();
        $mw = new TracingMiddleware('my_api', $collector, capturePayloads: true);
        $body = FakeFormBody::create(['name' => 'Ada']);
        $next = static fn (): never => throw new RequestResponseException(500, 'upstream body with a secret');

        try {
            $mw->process(FakePathAction::create('POST', '/items', $body), null, null, $next);
            self::fail('Expected exception to propagate.');
        } catch (RequestResponseException) {
        }

        $call = $collector->getCalls()[0];
        self::assertSame(['name' => 'Ada'], $call->bodyData);
        self::assertNull($call->responseData);
    }

    #[Test]
    public function processManyCapturesBodyContextAndResponsePerRequestWhenEnabled(): void
    {
        $collector = new IntegrationEngineDataCollector();
        $mw = new TracingMiddleware('my_api', $collector, capturePayloads: true);
        $body = FakeFormBody::create(['name' => 'Ada']);

        $mw->processMany(
            [
                'a' => new PreparedRequest(FakePathAction::create('POST', '/a', $body), DefaultActionContext::create(['id' => 1]), null),
                'b' => new PreparedRequest(FakePathAction::create('GET', '/b'), null, null),
            ],
            static fn (array $reqs): array => [
                'a' => ['body' => ['ok' => true], 'headers' => []],
                'b' => new RequestResponseException(500, 'boom'),
            ],
        );

        self::assertSame(['name' => 'Ada'], $collector->getCalls()[0]->bodyData);
        self::assertSame(['id' => 1], $collector->getCalls()[0]->contextData);
        self::assertSame(['ok' => true], $collector->getCalls()[0]->responseData);
        self::assertNull($collector->getCalls()[1]->responseData);
    }

    #[Test]
    public function processManyDoesNotCapturePayloadsByDefault(): void
    {
        $collector = new IntegrationEngineDataCollector();
        $mw = new TracingMiddleware('my_api', $collector);
        $body = FakeFormBody::create(['name' => 'Ada']);

        $mw->processMany(
            ['a' => new PreparedRequest(FakePathAction::create('POST', '/a', $body), DefaultActionContext::create(['id' => 1]), null)],
            static fn (array $reqs): array => ['a' => ['body' => ['ok' => true], 'headers' => []]],
        );

        $call = $collector->getCalls()[0];
        self::assertNull($call->bodyData);
        self::assertNull($call->contextData);
        self::assertNull($call->responseData);
    }

    // ── processMany() ─────────────────────────────────────────────────────────

    #[Test]
    public function processManyRecordsOneCallPerRequest(): void
    {
        $collector = new IntegrationEngineDataCollector();
        $mw = new TracingMiddleware('my_api', $collector);

        $result = $mw->processMany(
            [
                'a' => new PreparedRequest(FakePathAction::create('GET', '/a'), null, null),
                'b' => new PreparedRequest(FakePathAction::create('GET', '/b'), null, null),
            ],
            static fn (array $reqs): array => ['a' => ['x' => 1], 'b' => ['x' => 2]],
        );

        self::assertSame(['a' => ['x' => 1], 'b' => ['x' => 2]], $result);
        self::assertSame(2, $collector->getTotalCalls());
        self::assertSame('my_api', $collector->getCalls()[0]->integrationName);
    }

    #[Test]
    public function processManyRecordsTheActionBodyClassPerRequest(): void
    {
        $collector = new IntegrationEngineDataCollector();
        $mw = new TracingMiddleware('my_api', $collector);
        $body = FakeFormBody::create(['name' => 'Ada']);

        $mw->processMany(
            [
                'a' => new PreparedRequest(FakePathAction::create('POST', '/a', $body), null, null),
                'b' => new PreparedRequest(FakePathAction::create('GET', '/b'), null, null),
            ],
            static fn (array $reqs): array => ['a' => [], 'b' => []],
        );

        self::assertSame(FakeFormBody::class, $collector->getCalls()[0]->bodyClass);
        self::assertNull($collector->getCalls()[1]->bodyClass);
    }

    /**
     * The batch's total wall-clock time must be divided evenly across every
     * item it contains (concurrent requests share one measured duration,
     * see the production code's own comment) — not multiplied, and not
     * divided by an off-by-one item count.
     */
    #[Test]
    public function processManySplitsTheBatchDurationEvenlyAcrossTwoItems(): void
    {
        $collector = new IntegrationEngineDataCollector();
        $mw = new TracingMiddleware('my_api', $collector);
        $next = static function (array $reqs): array {
            usleep(20_000);

            return array_fill_keys(array_keys($reqs), []);
        };

        $mw->processMany(
            [
                'a' => new PreparedRequest(FakePathAction::create('GET', '/a'), null, null),
                'b' => new PreparedRequest(FakePathAction::create('GET', '/b'), null, null),
            ],
            $next,
        );

        $durationMs = $collector->getCalls()[0]->durationMs;
        // ~10ms per item (half of the 20ms batch) — bounded well below the
        // full batch duration to catch the split becoming a multiplication,
        // and well above 0 to catch the elapsed time being mismeasured.
        self::assertGreaterThan(1.0, $durationMs);
        self::assertLessThan(20.0, $durationMs);
    }

    /**
     * With exactly one item, the "divide by item count" and the "guard
     * against dividing by zero" floor must both resolve to dividing by
     * exactly 1 — not 2, which the max(1, …) floor could be off-by-one on.
     */
    #[Test]
    public function processManyDoesNotHalveTheDurationForASingleItemBatch(): void
    {
        $collector = new IntegrationEngineDataCollector();
        $mw = new TracingMiddleware('my_api', $collector);
        $next = static function (array $reqs): array {
            usleep(20_000);

            return array_fill_keys(array_keys($reqs), []);
        };

        $mw->processMany(['a' => new PreparedRequest(FakePathAction::create('GET', '/a'), null, null)], $next);

        $durationMs = $collector->getCalls()[0]->durationMs;
        self::assertGreaterThan(10.0, $durationMs);
        self::assertLessThan(1000.0, $durationMs);
    }

    /**
     * Regression: an empty batch must not divide the (zero) elapsed time
     * by an item count of zero.
     */
    #[Test]
    public function processManyWithNoRequestsDoesNotDivideByZero(): void
    {
        $collector = new IntegrationEngineDataCollector();
        $mw = new TracingMiddleware('my_api', $collector);

        $result = $mw->processMany([], static fn (array $reqs): array => []);

        self::assertSame([], $result);
        self::assertSame(0, $collector->getTotalCalls());
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
