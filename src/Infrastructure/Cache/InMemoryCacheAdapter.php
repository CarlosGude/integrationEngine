<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Cache;

use IntegrationEngine\Core\Port\CachePort;

final class InMemoryCacheAdapter implements CachePort
{
    /** @var array<string, array{value: mixed, expires_at: int}> */
    private array $store = [];

    public function get(string $key): mixed
    {
        return $this->store[$key]['value'] ?? null;
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        $this->store[$key] = [
            'value'      => $value,
            'expires_at' => time() + $ttl,
        ];
    }

    public function has(string $key): bool
    {
        if (!isset($this->store[$key])) {
            return false;
        }

        if (time() >= $this->store[$key]['expires_at']) {
            unset($this->store[$key]);
            return false;
        }

        return true;
    }
}
