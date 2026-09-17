<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionBodyInterface;
use IntegrationEngine\Core\Contract\Webhook\WebhookDefinition;
use IntegrationEngine\Core\Exception\ActionNotFoundException;
use IntegrationEngine\Core\Port\ConfigPort;

final class FakeConfigPort implements ConfigPort
{
    /** @var array<string, AbstractAction> */
    private array $actions = [];

    /** @var array<string, WebhookDefinition> */
    private array $webhooks = [];

    public function register(string $name, AbstractAction $action): void
    {
        $this->actions[$name] = $action;
    }

    public function registerWebhook(string $eventType, WebhookDefinition $definition): void
    {
        $this->webhooks[$eventType] = $definition;
    }

    public function getAction(string $name, ?ActionBodyInterface $bodyData = null): AbstractAction
    {
        if (!isset($this->actions[$name])) {
            throw new ActionNotFoundException($name);
        }

        return $this->actions[$name];
    }

    public function getWebhookDefinition(string $eventType): WebhookDefinition
    {
        if (!isset($this->webhooks[$eventType])) {
            throw new \InvalidArgumentException(\sprintf('Webhook event type "%s" not defined.', $eventType));
        }

        return $this->webhooks[$eventType];
    }
}
