<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Contract\Action\DefaultActionContext;
use IntegrationEngine\Infrastructure\Middleware\LoggingMiddleware;
use IntegrationEngine\Tests\Fake\FakeLogger;
use IntegrationEngine\Tests\Fake\FakePathAction;
use PHPUnit\Framework\TestCase;

final class LoggingMiddlewareTest extends TestCase
{
    public function testPassesRequestThroughAndLogsResolvedPathAndResponseStatus(): void
    {
        $logger = new FakeLogger();
        $action = FakePathAction::create('GET', '/orders/{id}');
        $context = DefaultActionContext::create(['id' => 42]);
        $response = ['body' => ['token' => 'secret'], 'headers' => [], 'statusCode' => 201];
        $actual = (new LoggingMiddleware($logger))->process($action, $context, null, static function ($receivedAction, $receivedContext, $headers) use ($action, $context, $response): array {
            self::assertSame($action, $receivedAction);
            self::assertSame($context, $receivedContext);
            self::assertNull($headers);
            usleep(20_000);

            return $response;
        });
        self::assertSame($response, $actual);
        self::assertCount(2, $logger->all());
        self::assertSame(['action' => 'fake_path_action', 'method' => 'GET', 'path' => '/orders/42'], $logger->contextFor('info', 'Request'));
        $logged = $logger->contextFor('info', 'Response');
        self::assertSame(201, $logged['status']);
        self::assertSame('fake_path_action', $logged['action']);
        self::assertIsInt($logged['duration_ms']);
        self::assertGreaterThanOrEqual(15, $logged['duration_ms']);
        self::assertLessThan(5000, $logged['duration_ms']);
        self::assertArrayNotHasKey('body', $logged);
    }

    public function testLogsFailureAndRethrowsTheSameThrowable(): void
    {
        $logger = new FakeLogger();
        $failure = new \RuntimeException('offline');

        try {
            (new LoggingMiddleware($logger))->process(FakePathAction::create('GET', '/orders'), null, null, static function () use ($failure): never {
                usleep(20_000);

                throw $failure;
            });
            self::fail('The failure must propagate');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
        self::assertCount(2, $logger->all());
        $logged = $logger->contextFor('error', 'Failure');
        self::assertSame('offline', $logged['error']);
        self::assertSame(\RuntimeException::class, $logged['type']);
        self::assertSame('fake_path_action', $logged['action']);
        self::assertIsInt($logged['duration_ms']);
        self::assertGreaterThanOrEqual(15, $logged['duration_ms']);
        self::assertLessThan(5000, $logged['duration_ms']);
    }

    public function testMissingStatusAndDefaultLoggerAreSupported(): void
    {
        $action = FakePathAction::create('GET', '/orders');
        $logger = new FakeLogger();
        (new LoggingMiddleware($logger))->process($action, null, null, static fn () => ['body' => [], 'headers' => []]);
        self::assertSame('unknown', $logger->contextFor('info', 'Response')['status']);
        self::assertSame(['body' => [], 'headers' => []], (new LoggingMiddleware())->process($action, null, null, static fn () => ['body' => [], 'headers' => []]));
    }
}
