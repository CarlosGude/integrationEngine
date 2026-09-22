<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook;

final class DotPath
{
    /** @param array<mixed> $payload */
    public static function get(array $payload, string $path): string|int|float|bool|null
    {
        $value = $payload;
        foreach (explode('.', $path) as $key) {
            if (!\is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return \is_scalar($value) ? $value : null;
    }
}
