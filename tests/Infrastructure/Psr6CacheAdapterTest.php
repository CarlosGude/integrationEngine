<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Infrastructure\Cache\Psr6CacheAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class Psr6CacheAdapterTest extends TestCase
{
    // ── get ──────────────────────────────────────────────────────────────────

    #[Test]
    public function getReturnsCachedValueOnHit(): void
    {
        $pool = new SpyCachePool();
        $pool->seed('my_key', 'my_value');
        $adapter = new Psr6CacheAdapter($pool);

        self::assertSame('my_value', $adapter->get('my_key'));
    }

    #[Test]
    public function getReturnsNullOnMiss(): void
    {
        $pool = new SpyCachePool();
        $adapter = new Psr6CacheAdapter($pool);

        self::assertNull($adapter->get('missing_key'));
    }

    // ── set ──────────────────────────────────────────────────────────────────

    #[Test]
    public function setStoresValueAndTtl(): void
    {
        $pool = new SpyCachePool();
        $adapter = new Psr6CacheAdapter($pool);

        $adapter->set('token', 'abc123', 300);

        self::assertSame('abc123', $adapter->get('token'));
        self::assertSame(300, $pool->lastTtl());
    }

    // ── key sanitization ─────────────────────────────────────────────────────

    #[Test]
    public function reservedPsr6CharactersAreSanitized(): void
    {
        $pool = new SpyCachePool();
        $adapter = new Psr6CacheAdapter($pool);

        // These characters are reserved in PSR-6: {}()/\@:
        $adapter->set('key{with}reserved(chars)/and\more@here:', 'value', 60);

        // The key reaching the pool must contain none of the reserved chars
        $sanitized = $pool->lastSetKey();
        self::assertNotNull($sanitized);
        self::assertDoesNotMatchRegularExpression('/[{}()\/\\\@:]/', $sanitized);
    }

    #[Test]
    public function dotsAreSanitized(): void
    {
        $pool = new SpyCachePool();
        $adapter = new Psr6CacheAdapter($pool);

        // The engine generates keys like integration_engine.token.action_name
        $adapter->set('integration_engine.token.fake_fetch_token', 'tok', 60);

        $sanitized = $pool->lastSetKey();
        self::assertNotNull($sanitized);
        self::assertStringNotContainsString('.', $sanitized);
    }
}

// ──────────────────────────────────────────────
// Inline fake
// ──────────────────────────────────────────────

final class SpyCacheItem implements CacheItemInterface
{
    private mixed $value;
    private ?int $ttl = null;

    public function __construct(
        private readonly string $key,
        private readonly bool $hit,
        mixed $storedValue,
    ) {
        $this->value = $storedValue;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(\DateInterval|int|null $time): static
    {
        $this->ttl = \is_int($time) ? $time : null;

        return $this;
    }

    public function getTtl(): ?int
    {
        return $this->ttl;
    }
}

final class SpyCachePool implements CacheItemPoolInterface
{
    /** @var array<string, mixed> */
    private array $store = [];
    private ?int $lastTtl = null;
    private ?string $lastSetKey = null;

    public function seed(string $key, mixed $value): void
    {
        $this->store[$key] = $value;
    }

    public function lastTtl(): ?int
    {
        return $this->lastTtl;
    }

    public function lastSetKey(): ?string
    {
        return $this->lastSetKey;
    }

    public function getItem(string $key): CacheItemInterface
    {
        $hit = \array_key_exists($key, $this->store);
        $value = $this->store[$key] ?? null;

        return new SpyCacheItem($key, $hit, $value);
    }

    public function save(CacheItemInterface $item): bool
    {
        $this->lastSetKey = $item->getKey();
        $this->store[$item->getKey()] = $item->get();
        if ($item instanceof SpyCacheItem) {
            $this->lastTtl = $item->getTtl();
        }

        return true;
    }

    public function hasItem(string $key): bool
    {
        return \array_key_exists($key, $this->store);
    }

    public function clear(): bool
    {
        $this->store = [];

        return true;
    }

    public function deleteItem(string $key): bool
    {
        unset($this->store[$key]);

        return true;
    }

    /** @param string[] $keys */
    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->store[$key]);
        }

        return true;
    }

    /** @return iterable<CacheItemInterface> */
    public function getItems(array $keys = []): iterable
    {
        foreach ($keys as $key) {
            yield $this->getItem($key);
        }
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    public function commit(): bool
    {
        return true;
    }
}
