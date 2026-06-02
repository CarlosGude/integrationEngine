<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Port;

use IntegrationEngine\Core\Contract\AbstractAction;

interface ConfigPort
{
    public function getAction(string $name): AbstractAction;
}
