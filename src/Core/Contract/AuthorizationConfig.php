<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

abstract readonly class AuthorizationConfig
{
    public const TYPE = 'type';

    public const DYNAMIC_TYPE = 'dynamic';
    public const STATIC_TYPE = 'static';

    public function __construct(
        public readonly string $type,
    ) {}

    public static function fromArray(array $config): self
    {
        if (!isset($config[self::TYPE])) {
            throw new \InvalidArgumentException('Authorization config must define a "type".');
        }

        return match ($config[self::TYPE]) {
            self::DYNAMIC_TYPE => DynamicAuthorizationConfig::fromArray($config),
            default => StaticAuthorizationConfig::fromArray($config),
        };
    }
}
