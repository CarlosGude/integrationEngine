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

    public function cacheKey(string $integrationName): string
    {
        return \sprintf('integration_engine.token.%s.%s', $integrationName, $this->action);
    }

    public function toStaticConfig(string $token): StaticAuthorizationConfig
    {
        $isDefaultHeader = 'Authorization' === $this->header;

        return new StaticAuthorizationConfig(
            type: $isDefaultHeader ? 'bearer' : 'api_key',
            params: $isDefaultHeader
                ? ['token' => $token, 'prefix' => $this->resolvedPrefix()]
                : ['header' => $this->header, 'token' => $token, 'prefix' => $this->resolvedPrefix()],
        );
    }

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        foreach (['action', 'token_field', 'ttl'] as $key) {
            if (!isset($config[$key])) {
                throw new \InvalidArgumentException(\sprintf('Dynamic authorization config must define "%s".', $key));
            }
        }

        if (!\is_string($config['action']) || !\is_string($config['token_field'])) {
            throw new \InvalidArgumentException('Dynamic authorization config fields "action" and "token_field" must be strings.');
        }
        if (!\is_int($config['ttl']) && !\is_float($config['ttl']) && !(\is_string($config['ttl']) && ctype_digit($config['ttl']))) {
            throw new \InvalidArgumentException('Dynamic authorization config field "ttl" must be a non-negative integer.');
        }
        if ((float) $config['ttl'] < 0) {
            throw new \InvalidArgumentException('Dynamic authorization config field "ttl" must be a non-negative integer.');
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
