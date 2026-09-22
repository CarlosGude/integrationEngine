<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook;

use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;

/**
 * Dispatches webhook events to the application domain.
 *
 * Converts RemoteEvent (HTTP-level) → WebhookEventInterface (domain-level)
 * via mapper, then dispatches as a Symfony domain event.
 *
 * @author Carlos Gude
 */
final class WebhookEventDispatcher
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {}

    /**
     * Parse a RemoteEvent and dispatch the typed domain event.
     *
     * @param RemoteEvent           $remoteEvent The parsed webhook event
     * @param AbstractWebhookMapper $mapper      Mapper for the specific event type
     * @param array<string, list<string>> $headers     Request headers
     *
     * @throws \InvalidArgumentException If mapper declaration doesn't match event type
     */
    public function dispatch(RemoteEvent $remoteEvent, AbstractWebhookMapper $mapper, array $headers): void
    {
        if ($mapper::eventType() !== $remoteEvent->getName()) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'Mapper declaration "%s" does not match event type "%s"',
                    $mapper::eventType(),
                    $remoteEvent->getName(),
                ),
            );
        }

        /** @var array<string, mixed> $payload */
        $payload = $remoteEvent->getPayload();
        $event = $mapper::map($remoteEvent->getName(), $payload, $headers);

        $this->dispatcher->dispatch($event);
    }
}
