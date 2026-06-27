<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Cache;

use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\Action\DefaultActionContext;
use IntegrationEngine\Infrastructure\Cache\CachingMiddleware;
use IntegrationEngine\Infrastructure\Debug\IntegrationEngineDataCollector;
use IntegrationEngine\Tests\Fake\FakeCache;
use IntegrationEngine\Tests\Fake\FakePathAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CachingMiddlewareTest extends TestCase
{
    // ── process(): no cache_ttl → passthrough ─────────────────────────────────

    #[Test]
    public function processDelegatesToNextWhenNoTtlIsSet(): void
    {
        $calls = 0;
        $next = static function () use (&$calls): array {
            ++$calls;

            return ['data' => 1];
        };
        $mw = new CachingMiddleware(new FakeCache(), 'my_api');

        $result = $mw->process(FakePathAction::create('GET', '/items'), null, null, $next);

        self::assertSame(['data' => 1], $result);
        self::assertSame(1, $calls);
    }

    // ── process(): cache miss → delegate + store ──────────────────────────────

    #[Test]
    public function processCallsNextOnMissAndStoresResult(): void
    {
        $calls = 0;
        $next = static function () use (&$calls): array {
            ++$calls;

            return ['data' => 42];
        };
        $cache = new FakeCache();
        $mw = new CachingMiddleware($cache, 'my_api');

        $result = $mw->process(FakePathAction::create('GET', '/items', cacheTtl: 60), null, null, $next);

        self::assertSame(['data' => 42], $result);
        self::assertSame(1, $calls);
        self::assertCount(1, $cache->all());
    }

    // ── process(): cache hit → skip next ─────────────────────────────────────

    #[Test]
    public function processReturnsCachedResultAndSkipsNextOnHit(): void
    {
        $calls = 0;
        $next = static function () use (&$calls): array {
            ++$calls;

            return ['data' => 42];
        };
        $cache = new FakeCache();
        $mw = new CachingMiddleware($cache, 'my_api');
        $action = FakePathAction::create('GET', '/items', cacheTtl: 60);

        $mw->process($action, null, null, $next);
        $second = $mw->process($action, null, null, $next);

        self::assertSame(['data' => 42], $second);
        self::assertSame(1, $calls);
    }

    // ── process(): cache hit records in collector ─────────────────────────────

    #[Test]
    public function processRecordsHitInCollectorWhenProvided(): void
    {
        $next = static fn (): array => ['data' => 1];
        $collector = new IntegrationEngineDataCollector();
        $mw = new CachingMiddleware(new FakeCache(), 'my_api', $collector);
        $action = FakePathAction::create('GET', '/items', cacheTtl: 60);

        $mw->process($action, null, null, $next);
        $mw->process($action, null, null, $next);

        self::assertSame(1, $collector->getCachedCount());
        self::assertTrue($collector->getCalls()[0]->cached);
    }

    // ── process(): different contexts → different keys ────────────────────────

    #[Test]
    public function processUsesSeparateCacheEntriesForDifferentContexts(): void
    {
        $calls = 0;
        $next = static function () use (&$calls): array {
            ++$calls;

            return [];
        };
        $cache = new FakeCache();
        $mw = new CachingMiddleware($cache, 'my_api');
        $action = FakePathAction::create('GET', '/items/{id}', cacheTtl: 60);

        $mw->process($action, DefaultActionContext::create(['id' => '1']), null, $next);
        $mw->process($action, DefaultActionContext::create(['id' => '2']), null, $next);

        self::assertSame(2, $calls);
        self::assertCount(2, $cache->all());
    }

    // ── processMany(): hits resolved, misses forwarded ────────────────────────

    #[Test]
    public function processManyServesHitsAndForwardsMissesToNext(): void
    {
        $calls = 0;
        $cache = new FakeCache();
        $mw = new CachingMiddleware($cache, 'my_api');
        $action = FakePathAction::create('GET', '/items/{id}', cacheTtl: 60);
        $ctx1 = DefaultActionContext::create(['id' => '1']);
        $ctx2 = DefaultActionContext::create(['id' => '2']);

        // Prime ctx1
        $mw->processMany(
            ['a' => new PreparedRequest($action, $ctx1, null)],
            static function (array $reqs) use (&$calls): array {
                ++$calls;

                return array_map(static fn () => ['val' => 1], $reqs);
            },
        );

        // Second call: ctx1 is hit, ctx2 is miss
        $forwarded = null;
        $results = $mw->processMany(
            [
                'a' => new PreparedRequest($action, $ctx1, null),
                'b' => new PreparedRequest($action, $ctx2, null),
            ],
            static function (array $reqs) use (&$calls, &$forwarded): array {
                ++$calls;
                $forwarded = array_keys($reqs);

                return array_map(static fn () => ['val' => 2], $reqs);
            },
        );

        self::assertSame(['val' => 1], $results['a']); // from cache
        self::assertSame(['val' => 2], $results['b']); // from next
        self::assertSame(['b'], $forwarded);
        self::assertSame(2, $calls);
    }

    #[Test]
    public function processManyDoesNotCacheThrowableResults(): void
    {
        $cache = new FakeCache();
        $mw = new CachingMiddleware($cache, 'my_api');

        $mw->processMany(
            ['a' => new PreparedRequest(FakePathAction::create('GET', '/items', cacheTtl: 60), null, null)],
            static fn (array $reqs): array => ['a' => new \RuntimeException('boom')],
        );

        self::assertEmpty($cache->all());
    }

    #[Test]
    public function processManySkipsItemsWithNoTtl(): void
    {
        $calls = 0;
        $cache = new FakeCache();
        $mw = new CachingMiddleware($cache, 'my_api');
        $action = FakePathAction::create('GET', '/items');

        $mw->processMany(['a' => new PreparedRequest($action, null, null)], static function (array $reqs) use (&$calls): array {
            ++$calls;

            return ['a' => []];
        });
        $mw->processMany(['a' => new PreparedRequest($action, null, null)], static function (array $reqs) use (&$calls): array {
            ++$calls;

            return ['a' => []];
        });

        self::assertSame(2, $calls);
        self::assertEmpty($cache->all());
    }
}
