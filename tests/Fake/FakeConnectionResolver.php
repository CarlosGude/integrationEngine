<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use IntegrationEngine\Core\Contract\Connection\ConnectionCredentials;
use IntegrationEngine\Core\Contract\Connection\ConnectionResolverInterface;

/** Resolves a scalar (string or int) connection id to whatever ConnectionCredentials a test registered for it. */
final class FakeConnectionResolver implements ConnectionResolverInterface
{
    /** @var array<int|string, ConnectionCredentials> */
    private array $credentials = [];

    /** @var array<int|string, int> */
    private array $callCount = [];

    public function register(int|string $connection, ConnectionCredentials $credentials): void
    {
        $this->credentials[$connection] = $credentials;
    }

    public function resolve(mixed $connection): ConnectionCredentials
    {
        if ((!\is_string($connection) && !\is_int($connection)) || !isset($this->credentials[$connection])) {
            throw new \InvalidArgumentException(\sprintf('No credentials registered for connection %s.', var_export($connection, true)));
        }

        $this->callCount[$connection] = ($this->callCount[$connection] ?? 0) + 1;

        return $this->credentials[$connection];
    }

    public function callCount(int|string $connection): int
    {
        return $this->callCount[$connection] ?? 0;
    }
}
