<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Exception;

final class PathResolutionException extends \RuntimeException
{
    public static function missingParameter(string $key, string $path): self
    {
        return new self(\sprintf('Missing path parameter "%s" for path "%s".', $key, $path));
    }

    public static function nonScalarParameter(string $key): self
    {
        return new self(\sprintf('Path parameter "%s" must be a scalar value.', $key));
    }
}
