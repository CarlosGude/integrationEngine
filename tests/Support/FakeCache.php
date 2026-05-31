<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Support;

use IntegrationEngine\Core\Port\CachePort;

final class FakeCache implements CachePort
{
    private array $data = [];

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }
}