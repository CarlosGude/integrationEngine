<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Core\Lifecycle\ActionCompleted;
use IntegrationEngine\Core\Lifecycle\ActionFailed;
use IntegrationEngine\Core\Lifecycle\ActionStarted;
use IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher;
use IntegrationEngine\Tests\Fake\FakeCache;
use IntegrationEngine\Tests\Fake\FakeClient;
use IntegrationEngine\Tests\Fake\FakeConfigPort;
use PHPUnit\Framework\TestCase;

final class IntegrationEngineLifecycleTest extends TestCase
{
    public function testDispatchesActionStartedBeforeSend(): void
    {
        $dispatcher = new LifecycleEventDispatcher();
        $events = [];

        $dispatcher->subscribe(ActionStarted::class, function (ActionStarted $e) use (&$events) {
            $events['started'] = $e;
        });

        $config = FakeConfigPort::withAction('GetUser');
        $client = FakeClient::respondsWith(['id' => 1, 'name' => 'Alice']);
        $cache = new FakeCache();

        $engine = new IntegrationEngine(
            config: $config,
            client: $client,
            cache: $cache,
            integrationName: 'stripe',
            eventDispatcher: $dispatcher,
        );

        $engine->send('GetUser');

        self::assertArrayHasKey('started', $events);
        self::assertSame('stripe', $events['started']->integrationName());
    }

    public function testDispatchesActionCompletedAfterSuccess(): void
    {
        $dispatcher = new LifecycleEventDispatcher();
        $events = [];

        $dispatcher->subscribe(ActionCompleted::class, function (ActionCompleted $e) use (&$events) {
            $events['completed'] = $e;
        });

        $config = FakeConfigPort::withAction('GetUser');
        $client = FakeClient::respondsWith(['id' => 1, 'name' => 'Alice']);
        $cache = new FakeCache();

        $engine = new IntegrationEngine(
            config: $config,
            client: $client,
            cache: $cache,
            integrationName: 'stripe',
            eventDispatcher: $dispatcher,
        );

        $engine->send('GetUser');

        self::assertArrayHasKey('completed', $events);
        self::assertGreaterThan(0, $events['completed']->durationMs());
        self::assertNotNull($events['completed']->response());
    }

    public function testDispatchesActionFailedOnError(): void
    {
        $dispatcher = new LifecycleEventDispatcher();
        $events = [];

        $dispatcher->subscribe(ActionFailed::class, function (ActionFailed $e) use (&$events) {
            $events['failed'] = $e;
        });

        $config = FakeConfigPort::withAction('GetUser');
        $client = FakeClient::throwsException(new \Exception('Network timeout'));
        $cache = new FakeCache();

        $engine = new IntegrationEngine(
            config: $config,
            client: $client,
            cache: $cache,
            integrationName: 'stripe',
            eventDispatcher: $dispatcher,
        );

        try {
            $engine->send('GetUser');
        } catch (\Exception $e) {
            // Expected
        }

        self::assertArrayHasKey('failed', $events);
        self::assertSame('Network timeout', $events['failed']->error()->getMessage());
        self::assertGreaterThan(0, $events['failed']->durationMs());
    }

    public function testNoDispatcherMeansNoEvents(): void
    {
        $config = FakeConfigPort::withAction('GetUser');
        $client = FakeClient::respondsWith(['id' => 1, 'name' => 'Alice']);
        $cache = new FakeCache();

        $engine = new IntegrationEngine(
            config: $config,
            client: $client,
            cache: $cache,
            integrationName: 'stripe',
            // eventDispatcher: null (default)
        );

        // Should not throw; events just aren't dispatched
        $engine->send('GetUser');

        self::assertTrue(true);
    }
}
