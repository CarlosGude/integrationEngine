<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Exception;

final class DisallowedHostException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Request destination is not allowed by the integration host policy.');
    }
}
