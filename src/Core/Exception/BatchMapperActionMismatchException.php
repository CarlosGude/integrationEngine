<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Exception;

final class BatchMapperActionMismatchException extends \RuntimeException
{
    public function __construct(
        string $mapperClass,
        string $expectedActionClass,
        string $key,
        string $actualActionClass,
    ) {
        parent::__construct(
            \sprintf(
                'Batch mapper "%s" expects action "%s" but key "%s" has action "%s".',
                $mapperClass,
                $expectedActionClass,
                $key,
                $actualActionClass,
            )
        );
    }
}
