<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

abstract readonly class AuthorizationConfig
{
    public function __construct(
        public readonly string $type,
    ) {
    }

    public static function fromArray(array $config): self
    {
        if (!isset($config['type'])) {
            throw new \InvalidArgumentException('Authorization config must define a "type".');
        }

        return match ($config['type']) {
            'dynamic' => DynamicAuthorizationConfig::fromArray($config),
            default => StaticAuthorizationConfig::fromArray($config),
        };
    }
}
