<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

final readonly class DynamicAuthorizationConfig extends AuthorizationConfig
{
    /**
     * @param null|string $prefix null means "default for the header":
     *                            "Bearer" for Authorization, none otherwise
     */
    public function __construct(
        public string $action,
        public string $tokenField,
        public int $ttl,
        public string $header = 'Authorization',
        public ?string $prefix = null,
    ) {
        parent::__construct('dynamic');
    }

    public function resolvedPrefix(): string
    {
        return $this->prefix ?? ('Authorization' === $this->header ? 'Bearer' : '');
    }

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        foreach (['action', 'token_field', 'ttl'] as $key) {
            if (!isset($config[$key])) {
                throw new \InvalidArgumentException(\sprintf('Dynamic authorization config must define "%s".', $key));
            }
        }

        if (!\is_string($config['action']) || !\is_string($config['token_field']) || !\is_scalar($config['ttl'])) {
            throw new \InvalidArgumentException('Dynamic authorization config fields "action", "token_field" must be strings and "ttl" must be scalar.');
        }

        return new self(
            action: $config['action'],
            tokenField: $config['token_field'],
            ttl: (int) $config['ttl'],
            header: isset($config['header']) && \is_string($config['header']) ? $config['header'] : 'Authorization',
            prefix: isset($config['prefix']) && \is_string($config['prefix']) ? $config['prefix'] : null,
        );
    }
}
