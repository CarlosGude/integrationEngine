<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

final readonly class DynamicAuthorizationConfig extends AuthorizationConfig
{
    public function __construct(
        public readonly string $action,
        public readonly string $tokenField,
        public readonly int $ttl,
    ) {
        parent::__construct('dynamic');
    }

    public static function fromArray(array $config): self
    {
        foreach (['action', 'token_field', 'ttl'] as $key) {
            if (!isset($config[$key])) {
                throw new \InvalidArgumentException(sprintf('Dynamic authorization config must define "%s".', $key));
            }
        }

        return new self(
            action: $config['action'],
            tokenField: $config['token_field'],
            ttl: (int) $config['ttl'],
        );
    }
}
