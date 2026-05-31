<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Exception;

final class MapperActionMismatchException extends \RuntimeException
{
    public function __construct(string $mapperClass, string $expectedActionClass, string $actualActionClass)
    {
        parent::__construct(
            \sprintf(
                'Mapper "%s" expects action "%s" but received "%s".',
                $mapperClass,
                $expectedActionClass,
                $actualActionClass,
            )
        );
    }
}
