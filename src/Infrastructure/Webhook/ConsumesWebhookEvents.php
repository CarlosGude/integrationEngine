<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook;

use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use Symfony\Component\RemoteEvent\RemoteEvent;

/**
 * The consume() every webhook consumer writes: map the verified event and
 * dispatch the typed DTO.
 *
 * A consumer that uses this declares its mapper and nothing else:
 *
 *     #[AsRemoteEventConsumer('stripe_charge_succeeded')]
 *     final readonly class ChargeSucceededConsumer implements ConsumerInterface
 *     {
 *         use ConsumesWebhookEvents;
 *
 *         protected function mapper(): AbstractWebhookMapper
 *         {
 *             return new ChargeSucceededEventMapper();
 *         }
 *     }
 *
 * The constructor comes from this trait. A consumer that needs more
 * dependencies declares its own and aliases this one to keep the dispatcher:
 *
 *     use ConsumesWebhookEvents { __construct as private withDispatcher; }
 *
 * @author Carlos Gude
 */
trait ConsumesWebhookEvents
{
    public function __construct(
        private readonly WebhookEventDispatcher $dispatcher,
    ) {}

    public function consume(RemoteEvent $event): void
    {
        $mapper = $this->mapper();

        if (!$this->handles($event, $mapper)) {
            return;
        }

        $this->dispatcher->dispatch($event, $mapper, []);
    }

    /**
     * Maps the payload of the event type this consumer answers to.
     */
    abstract protected function mapper(): AbstractWebhookMapper;

    /**
     * Every event parsed at one URL is named after that parser's
     * getDefinition(), so a provider posting several event types to the same
     * URL has to be told apart here — WebhookEventDispatcher throws when the
     * mapper's definition doesn't match the event name.
     *
     * The payload's "type" is the Stripe-style convention. Override this for a
     * provider that says it elsewhere, or return true when one URL only ever
     * carries one type.
     */
    protected function handles(RemoteEvent $event, AbstractWebhookMapper $mapper): bool
    {
        $type = $event->getPayload()['type'] ?? null;

        return null === $type || $type === $mapper->getDefinition();
    }
}
