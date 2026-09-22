<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Webhook;

use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;
use IntegrationEngine\Infrastructure\Webhook\WebhookEventDispatcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\RemoteEvent\RemoteEvent;

final class WebhookEventDispatcherTest extends TestCase
{
    public function testRejectsAnIncompatibleMapperWithoutMappingOrDispatching(): void
    {
        $received = [];
        $events = new EventDispatcher();
        $events->addListener(DispatcherTestEvent::class, static function (DispatcherTestEvent $event) use (&$received): void {
            $received[] = $event;
        });
        $mapper = new DispatcherTestMapper();
        $dispatcher = new WebhookEventDispatcher($events);

        try {
            $dispatcher->dispatch(new RemoteEvent('orders.created', 'evt_1', ['id' => 123]), $mapper, []);
            self::fail('Expected an incompatible mapper to be rejected');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Mapper declaration "payment.completed" does not match event type "orders.created"', $e->getMessage());
        }

        self::assertSame(0, $mapper->calls);
        self::assertSame([], $received);
    }

    public function testMapsThePayloadAndHeadersAndDispatchesTheMappedEvent(): void
    {
        $received = [];
        $events = new EventDispatcher();
        $events->addListener(DispatcherTestEvent::class, static function (DispatcherTestEvent $event) use (&$received): void {
            $received[] = $event;
        });
        $mapper = new DispatcherTestMapper();
        $payload = ['id' => 123, 'amount' => 42];
        $headers = ['x-provider' => 'payments'];

        (new WebhookEventDispatcher($events))->dispatch(new RemoteEvent('payment.completed', 'evt_1', $payload), $mapper, $headers);

        self::assertSame(1, $mapper->calls);
        self::assertCount(1, $received);
        self::assertSame($payload, $received[0]->payload);
        self::assertSame($headers, $received[0]->headers);
    }
}

final class DispatcherTestMapper extends AbstractWebhookMapper
{
    public int $calls = 0;

    public function getDefinition(): string
    {
        return 'payment.completed';
    }

    public function map(array $payload, array $headers): WebhookEventInterface
    {
        ++$this->calls;

        return new DispatcherTestEvent($payload, $headers);
    }
}

final class DispatcherTestEvent implements WebhookEventInterface
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $headers
     */
    public function __construct(
        public readonly array $payload,
        public readonly array $headers,
    ) {}
}
