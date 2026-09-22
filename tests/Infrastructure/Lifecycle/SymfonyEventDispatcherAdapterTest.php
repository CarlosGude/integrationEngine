<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Lifecycle;

use IntegrationEngine\Core\Event\RequestSent;
use IntegrationEngine\Infrastructure\Lifecycle\SymfonyEventDispatcherAdapter;
use IntegrationEngine\Tests\Fake\FakeTokenAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class SymfonyEventDispatcherAdapterTest extends TestCase
{
    #[Test]
    public function forwardsEventsToSymfonyListenersAndDirectSubscribers(): void
    {
        $symfony = new EventDispatcher();
        $viaSymfony = [];
        $symfony->addListener(RequestSent::class, static function (RequestSent $event) use (&$viaSymfony): void {
            $viaSymfony[] = $event;
        });

        // Constructing it used to fail: "Cannot call constructor".
        $adapter = new SymfonyEventDispatcherAdapter($symfony);
        $viaSubscribe = [];
        $adapter->subscribe(RequestSent::class, static function (RequestSent $event) use (&$viaSubscribe): void {
            $viaSubscribe[] = $event;
        });

        $event = new RequestSent('test_integration', FakeTokenAction::getName(), 'GET', '/token', microtime(true));
        $adapter->dispatch($event);

        self::assertSame([$event], $viaSymfony);
        self::assertSame([$event], $viaSubscribe);
    }
}
