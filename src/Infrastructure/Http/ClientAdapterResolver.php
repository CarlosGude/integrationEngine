<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Http;

use IntegrationEngine\Core\Contract\ClientAdapterInterface;

final class ClientAdapterResolver
{
    /** @var array<string, class-string<ClientAdapterInterface>> */
    private array $adapters = [];

    /**
     * Register an adapter class for a given client type.
     * Later registrations override earlier ones — project adapters
     * are registered after bundle built-ins, so they always win.
     *
     * @param class-string<ClientAdapterInterface> $adapterClass
     */
    public function register(string $clientType, string $adapterClass): void
    {
        $this->adapters[$clientType] = $adapterClass;
    }

    /**
     * @return class-string<ClientAdapterInterface>
     *
     * @throws \InvalidArgumentException when the client type is not registered
     */
    public function resolve(string $clientType): string
    {
        if (!isset($this->adapters[$clientType])) {
            throw new \InvalidArgumentException(\sprintf(
                'Unknown client type "%s". Registered types: %s.',
                $clientType,
                implode(', ', array_keys($this->adapters)) ?: 'none'
            ));
        }

        return $this->adapters[$clientType];
    }

    /** @return array<string, class-string<ClientAdapterInterface>> */
    public function all(): array
    {
        return $this->adapters;
    }
}
