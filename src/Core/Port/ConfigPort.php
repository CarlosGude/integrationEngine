<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Port;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\ActionBodyInterface;

interface ConfigPort
{
    public function getAction(string $name, ?ActionBodyInterface $body = null): AbstractAction;
}
