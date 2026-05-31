<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Port;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\ActionBodyInterface;

interface ConfigPort
{
    /**
     * @throws \InvalidArgumentException if the action is not found or config is malformed
     */
    public function getAction(
        string $actionName,
        ?ActionBodyInterface $body = null,
    ): AbstractAction;
}
