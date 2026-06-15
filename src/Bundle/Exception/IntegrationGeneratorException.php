<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Exception;

final class IntegrationGeneratorException extends \RuntimeException
{
    public static function cannotCreateDirectory(string $dir): self
    {
        return new self("Could not create config directory: {$dir}");
    }

    public static function cannotWriteFile(string $path): self
    {
        return new self("Could not write config file: {$path}");
    }
}
