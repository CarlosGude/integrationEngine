<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Batch\EngineRequest;
use IntegrationEngine\Core\Contract\Auth\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Tests\Fake\FakeLogger;
use IntegrationEngine\Tests\Fake\FakeProtectedAction;
use IntegrationEngine\Tests\Fake\FakeTokenAction;
use PHPUnit\Framework\Attributes\Test;

final class LoggingTest extends IntegrationEngineTestCase
{
    private FakeLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = new FakeLogger();
        $this->engine = new IntegrationEngine(
            config: $this->config,
            client: $this->client,
            cache: $this->cache,
            integrationName: 'test_integration',
            logger: $this->logger,
        );
    }

    // ── token fetch ───────────────────────────────────────────────────────────

    #[Test]
    public function tokenFetchIsLoggedAtInfoLevel(): void
    {
        $this->registerProtectedPair();
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'tok']);
        $this->client->setResponse(FakeProtectedAction::getName(), []);

        $this->engine->send(FakeProtectedAction::getName());

        self::assertTrue($this->logger->hasEntry('info', 'Fetching dynamic auth token'));
    }

    #[Test]
    public function tokenCacheHitIsLoggedAtDebugLevel(): void
    {
        $this->registerProtectedPair();
        $this->cache->set(
            'integration_engine.token.test_integration.'.FakeTokenAction::getName(),
            'cached_token',
            60,
        );
        $this->client->setResponse(FakeProtectedAction::getName(), []);

        $this->engine->send(FakeProtectedAction::getName());

        self::assertTrue($this->logger->hasEntry('debug', 'cache hit'));
        self::assertFalse($this->logger->hasEntry('info', 'Fetching dynamic auth token'));
    }

    // ── single 401 retry ─────────────────────────────────────────────────────

    #[Test]
    public function singleRetryAfter401IsLoggedAtWarningLevel(): void
    {
        $cacheKey = 'integration_engine.token.test_integration.'.FakeTokenAction::getName();
        $this->cache->set($cacheKey, 'stale', 60);
        $this->registerProtectedPair();
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'fresh']);
        $this->client->setResponse(FakeProtectedAction::getName(), []);
        $this->client->queueException(FakeProtectedAction::getName(), new RequestResponseException(401, 'revoked'));

        $this->engine->send(FakeProtectedAction::getName());

        self::assertTrue($this->logger->hasEntry('warning', 'Cached auth token rejected (401)'));
    }

    // ── batch 401 retry ───────────────────────────────────────────────────────

    #[Test]
    public function batchRetryAfter401IsLoggedAtWarningLevel(): void
    {
        $cacheKey = 'integration_engine.token.test_integration.'.FakeTokenAction::getName();
        $this->cache->set($cacheKey, 'stale', 60);
        $this->registerProtectedPair();
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'fresh']);
        $this->client->setResponse(FakeProtectedAction::getName(), []);
        $this->client->queueException(FakeProtectedAction::getName(), new RequestResponseException(401, 'revoked'));

        $this->engine->sendMany(['one' => EngineRequest::create(FakeProtectedAction::getName())]);

        self::assertTrue($this->logger->hasEntry('warning', 'Retrying batch items'));
    }

    private function registerProtectedPair(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
    }
}
