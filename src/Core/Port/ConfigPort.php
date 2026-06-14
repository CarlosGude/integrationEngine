<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Port;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionBodyInterface;

interface ConfigPort
{
    public function getAction(string $name, ?ActionBodyInterface $bodyData): AbstractAction;
}
