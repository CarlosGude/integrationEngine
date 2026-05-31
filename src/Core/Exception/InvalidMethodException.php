<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Exception;

final class InvalidMethodException extends \InvalidArgumentException
{
    public const string ERROR = 'INVALID_METHOD';

    public function __construct()
    {
        parent::__construct(self::ERROR);
    }
}
