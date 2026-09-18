<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core\Lifecycle;

use IntegrationEngine\Core\Lifecycle\ActionCompleted;
use IntegrationEngine\Core\Lifecycle\ActionFailed;
use IntegrationEngine\Core\Lifecycle\ActionStarted;
use IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher;
use IntegrationEngine\Tests\Fake\FakeAction;
use PHPUnit\Framework\TestCase;

final class LifecycleEventDispatcherTest extends TestCase
{
    public function testDispatchesActionStarted(): void
    {
        $dispatcher = new LifecycleEventDispatcher();
        $received = null;

        $dispatcher->subscribe(ActionStarted::class, function (ActionStarted $e) use (&$received) {
            $received = $e;
        });

        $action = FakeAction::create();
        $event = new ActionStarted('stripe', 1234567890.123);

        $dispatcher->dispatch($event);

        self::assertNotNull($received);
        self::assertSame('stripe', $received->integrationName());
        self::assertSame(1234567890.123, $received->timestamp());
    }

    public function testDispatchesActionCompleted(): void
    {
        $dispatcher = new LifecycleEventDispatcher();
        $received = null;

        $dispatcher->subscribe(ActionCompleted::class, function (ActionCompleted $e) use (&$received) {
            $received = $e;
        });

        $action = FakeAction::create();
        $response = $action->hasResponse() ? new \stdClass() : null;
        $event = new ActionCompleted(
            action: $action,
            integrationName: 'stripe',
            timestamp: 1234567890.123,
            response: $response,
            durationMs: 125.5,
        );

        $dispatcher->dispatch($event);

        self::assertNotNull($received);
        self::assertSame('stripe', $received->integrationName());
        self::assertSame(125.5, $received->durationMs());
    }

    public function testDispatchesActionFailed(): void
    {
        $dispatcher = new LifecycleEventDispatcher();
        $received = null;

        $dispatcher->subscribe(ActionFailed::class, function (ActionFailed $e) use (&$received) {
            $received = $e;
        });

        $action = FakeAction::create();
        $error = new \Exception('API timeout');
        $event = new ActionFailed(
            action: $action,
            integrationName: 'stripe',
            timestamp: 1234567890.123,
            error: $error,
            durationMs: 5001.0,
        );

        $dispatcher->dispatch($event);

        self::assertNotNull($received);
        self::assertSame('API timeout', $received->error()->getMessage());
        self::assertSame(5001.0, $received->durationMs());
    }

    public function testNoSubscribersNoOp(): void
    {
        $dispatcher = new LifecycleEventDispatcher();
        $action = FakeAction::create();
        $event = new ActionStarted('stripe', 1234567890.123);

        // Should not throw even with no subscribers
        $dispatcher->dispatch($event);

        self::assertTrue(true);
    }

    public function testMultipleSubscribers(): void
    {
        $dispatcher = new LifecycleEventDispatcher();
        $calls = [];

        $dispatcher->subscribe(ActionStarted::class, function () use (&$calls) {
            $calls[] = 'subscriber1';
        });

        $dispatcher->subscribe(ActionStarted::class, function () use (&$calls) {
            $calls[] = 'subscriber2';
        });

        $event = new ActionStarted('stripe', 1234567890.123);
        $dispatcher->dispatch($event);

        self::assertSame(['subscriber1', 'subscriber2'], $calls);
    }
}
