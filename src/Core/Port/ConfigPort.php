<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Port;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionBodyInterface;
use IntegrationEngine\Core\Contract\Webhook\WebhookDefinition;

interface ConfigPort
{
    public function getAction(string $name, ?ActionBodyInterface $bodyData): AbstractAction;

    public function getWebhookDefinition(): ?WebhookDefinition;
}
