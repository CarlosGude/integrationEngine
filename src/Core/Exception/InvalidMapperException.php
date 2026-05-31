<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Exception;

final class InvalidMapperException extends \RuntimeException
{
    public function __construct(string $mapperClass)
    {
        parent::__construct(
            sprintf('Mapper "%s" must extend %s.', $mapperClass, \IntegrationEngine\Core\Contract\AbstractMapper::class)
        );
    }
}
