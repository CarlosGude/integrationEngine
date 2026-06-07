<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Cache;

use IntegrationEngine\Core\Port\CachePort;
use Psr\Cache\CacheItemPoolInterface;

final class Psr6CacheAdapter implements CachePort
{
    public function __construct(
        private readonly CacheItemPoolInterface $pool,
    ) {}

    public function get(string $key): mixed
    {
        $item = $this->pool->getItem($this->sanitizeKey($key));

        return $item->isHit() ? $item->get() : null;
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        $item = $this->pool->getItem($this->sanitizeKey($key));
        $item->set($value);
        $item->expiresAfter($ttl);
        $this->pool->save($item);
    }

    public function has(string $key): bool
    {
        return $this->pool->hasItem($this->sanitizeKey($key));
    }

    /**
     * PSR-6 keys may not contain reserved characters: {}()/\@:
     * Some pools also reject dots — we replace all of them with underscores.
     */
    private function sanitizeKey(string $key): string
    {
        return strtr($key, '{}()/\@:.', '_________');
    }
}