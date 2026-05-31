<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

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

    public static function fromArray(array $config): self
    {
        $type = $config['type'];
        unset($config['type']);

        return new self(type: $type, params: $config);
    }
}
