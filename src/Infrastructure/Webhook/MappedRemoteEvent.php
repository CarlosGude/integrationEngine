<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook;

use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;

final class MappedRemoteEvent extends RemoteEvent
{
    /** @param array<mixed> $payload */
    public function __construct(string $name, string $id, array $payload, private readonly WebhookEventInterface $event)
    {
        parent::__construct($name, $id, $payload);
    }

    public function event(): WebhookEventInterface
    {
        return $this->event;
    }
}
