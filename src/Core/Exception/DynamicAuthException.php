<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Exception;

final class DynamicAuthException extends \RuntimeException
{
    public static function missingTokenField(string $action, string $field): self
    {
        return new self(\sprintf(
            'Dynamic auth action "%s" response does not contain field "%s".',
            $action,
            $field
        ));
    }

    public static function nonScalarTokenField(string $field): self
    {
        return new self(\sprintf('Token field "%s" must be a scalar value.', $field));
    }
}
