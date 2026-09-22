<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core\Webhook;

use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;
use PHPUnit\Framework\TestCase;

final class AbstractWebhookMapperTest extends TestCase
{
    public function testRejectsEventTypeMismatch(): void
    {
        $this->expectException(\IntegrationEngine\Core\Exception\WebhookMapperMismatchException::class);
        TestWebhookMapper::map('other.event', [], []);
    }

    public function testMapperMapsPayloadToEvent(): void
    {
        $mapper = new TestWebhookMapper();
        $payload = [
            'id' => 'evt_123',
            'type' => 'charge.succeeded',
            'data' => [
                'amount' => 1000,
                'currency' => 'usd',
            ],
        ];
        $headers = ['content-type' => ['application/json']];

        $event = $mapper::map('charge.succeeded', $payload, $headers);

        self::assertInstanceOf(TestWebhookEvent::class, $event);

        /** @var TestWebhookEvent $event */
        self::assertSame('evt_123', $event->eventId);
        self::assertSame('charge.succeeded', $event->type);
    }

    public function testMapperDeclaresDefinition(): void
    {
        $mapper = new TestWebhookMapper();

        self::assertSame('charge.succeeded', $mapper::eventType());
    }

    public function testEventIsSerializable(): void
    {
        $mapper = new TestWebhookMapper();
        $payload = [
            'id' => 'evt_456',
            'type' => 'charge.succeeded',
            'data' => ['amount' => 2000],
        ];

        $event = $mapper::map('charge.succeeded', $payload, []);

        // Serialize and unserialize
        $serialized = serialize($event);
        $unserialized = unserialize($serialized);

        self::assertInstanceOf(TestWebhookEvent::class, $unserialized);

        /** @var TestWebhookEvent $unserialized */
        self::assertSame('evt_456', $unserialized->eventId);
        self::assertSame('charge.succeeded', $unserialized->type);
    }

    public function testEventWithComplexPayload(): void
    {
        $mapper = new TestWebhookMapper();
        $payload = [
            'id' => 'evt_789',
            'type' => 'charge.succeeded',
            'data' => [
                'amount' => 5000,
                'currency' => 'eur',
                'metadata' => [
                    'order_id' => 'ord_123',
                    'customer' => 'cust_456',
                ],
            ],
        ];

        $event = $mapper::map('charge.succeeded', $payload, []);

        self::assertInstanceOf(TestWebhookEvent::class, $event);

        /** @var TestWebhookEvent $event */
        self::assertSame('evt_789', $event->eventId);
        self::assertSame(5000, $event->amount);
        self::assertSame('eur', $event->currency);
    }
}

/**
 * Test webhook event implementation.
 */
final readonly class TestWebhookEvent implements WebhookEventInterface
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $type,
        public readonly int $amount = 0,
        public readonly string $currency = 'usd',
    ) {}
}

/**
 * Test webhook mapper implementation.
 */
final class TestWebhookMapper extends AbstractWebhookMapper
{
    public static function eventType(): string
    {
        return 'charge.succeeded';
    }

    protected static function transform(array $payload, array $headers): WebhookEventInterface
    {
        /** @var array{id: string, type: string, data: array{amount?: int, currency?: string}} $payload */
        return new TestWebhookEvent(
            eventId: $payload['id'],
            type: $payload['type'],
            amount: $payload['data']['amount'] ?? 0,
            currency: $payload['data']['currency'] ?? 'usd',
        );
    }
}
