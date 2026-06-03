<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Exception;

class ActionNotFoundException extends \InvalidArgumentException
{
    public function __construct(string $actionName)
    {
        parent::__construct("Action [{$actionName}] not found");
    }
}
