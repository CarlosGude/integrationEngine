<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Exception;

final class NotMappedActionException extends \RuntimeException
{
    public function __construct(string $action)
    {
        parent::__construct(
            \sprintf('Action "%s" requires a mapper but none was defined.', $action)
        );
    }
}
