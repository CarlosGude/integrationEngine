<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Auth;

final readonly class StaticAuthorizationConfig extends AuthorizationConfig
{
    /**
     * @param array<string, mixed> $params e.g. ['token' => 'sk_live_...']
     */
    public function __construct(
        string $type,
        public readonly array $params = [],
    ) {
        parent::__construct($type);
    }

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        if (!isset($config['type']) || !\is_string($config['type'])) {
            throw new \InvalidArgumentException('Static authorization config must define a string "type".');
        }

        $type = $config['type'];
        unset($config['type']);

        return new self(type: $type, params: $config);
    }
}
