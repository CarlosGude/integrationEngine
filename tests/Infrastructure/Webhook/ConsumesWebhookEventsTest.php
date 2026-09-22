<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Webhook;

use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;
use IntegrationEngine\Infrastructure\Webhook\ConsumesWebhookEvents;
use IntegrationEngine\Infrastructure\Webhook\WebhookEventDispatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;

/**
 * The consume() that every generated consumer inherits.
 */
final class ConsumesWebhookEventsTest extends TestCase
{
    /** @var list<ConsumedTestEvent> */
    private array $received = [];

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->eventDispatcher->addListener(
            ConsumedTestEvent::class,
            function (ConsumedTestEvent $event): void {
                $this->received[] = $event;
            },
        );
    }

    #[Test]
    public function mapsAndDispatchesTheEventItAnswersTo(): void
    {
        $this->consumer()->consume(new RemoteEvent('products/update', 'evt_1', [
            'type' => 'products/update',
            'id' => 123,
        ]));

        self::assertCount(1, $this->received);
        self::assertSame('123', $this->received[0]->id);
    }

    #[Test]
    public function skipsAnEventOfAnotherTypeReachingTheSameUrl(): void
    {
        // Without this the dispatcher would throw: the mapper's definition
        // does not match the name the parser gave the event.
        $this->consumer()->consume(new RemoteEvent('products/update', 'evt_2', [
            'type' => 'orders/create',
            'id' => 456,
        ]));

        self::assertSame([], $this->received);
    }

    #[Test]
    public function handlesAPayloadThatCarriesNoTypeAtAll(): void
    {
        // Providers that send the event type as a header leave nothing in the
        // payload to discriminate on: the event is handled, not silently
        // dropped.
        $this->consumer()->consume(new RemoteEvent('products/update', 'evt_3', ['id' => 789]));

        self::assertCount(1, $this->received);
        self::assertSame('789', $this->received[0]->id);
    }

    #[Test]
    public function aConsumerCanOverrideHowEventsAreToldApart(): void
    {
        // The provider says the type somewhere else — here, a "topic" key.
        $consumer = new TopicConsumer(new WebhookEventDispatcher($this->eventDispatcher));

        $consumer->consume(new RemoteEvent('products/update', 'evt_4', ['topic' => 'products/update', 'id' => 2]));

        self::assertCount(1, $this->received);
        self::assertSame('2', $this->received[0]->id);
    }

    #[Test]
    public function anOverriddenCheckAlsoDecidesWhatToSkip(): void
    {
        $consumer = new TopicConsumer(new WebhookEventDispatcher($this->eventDispatcher));

        $consumer->consume(new RemoteEvent('products/update', 'evt_5', ['topic' => 'orders/create', 'id' => 1]));

        self::assertSame([], $this->received);
    }

    private function consumer(): ConsumerInterface
    {
        return new class(new WebhookEventDispatcher($this->eventDispatcher)) implements ConsumerInterface {
            use ConsumesWebhookEvents;

            protected function mapper(): AbstractWebhookMapper
            {
                return new ConsumedTestMapper();
            }
        };
    }
}

/**
 * Minimal typed event for the consumer test.
 */
final class ConsumedTestEvent implements WebhookEventInterface
{
    public function __construct(
        public readonly string $id,
    ) {}
}

/**
 * Maps products/update payloads to ConsumedTestEvent.
 */
final class ConsumedTestMapper extends AbstractWebhookMapper
{
    public static function eventType(): string
    {
        return 'products/update';
    }

    protected static function transform(array $payload, array $headers): WebhookEventInterface
    {
        $id = $payload['id'] ?? '';

        return new ConsumedTestEvent(\is_scalar($id) ? (string) $id : '');
    }
}

/**
 * A consumer built on the trait, ready to be specialised.
 */
class BaseTopicConsumer implements ConsumerInterface
{
    use ConsumesWebhookEvents;

    protected function mapper(): AbstractWebhookMapper
    {
        return new ConsumedTestMapper();
    }
}

/**
 * Tells events apart by a "topic" key instead of the default "type".
 */
final class TopicConsumer extends BaseTopicConsumer
{
    protected function handles(RemoteEvent $event, AbstractWebhookMapper $mapper): bool
    {
        return ($event->getPayload()['topic'] ?? null) === $mapper::eventType();
    }
}
