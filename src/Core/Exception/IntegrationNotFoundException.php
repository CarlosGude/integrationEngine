<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Exception;

final class IntegrationNotFoundException extends \InvalidArgumentException
{
    public function __construct(string $name)
    {
        parent::__construct(
            \sprintf('Integration "%s" is not registered. Did you configure it under integration_engine.integrations?', $name)
        );
    }
}
