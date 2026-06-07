<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use IntegrationEngine\Core\Port\CachePort;

final class FakeCache implements CachePort
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        $this->data[$key] = $value;
    }
}
