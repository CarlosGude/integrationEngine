<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Contract\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Contract\StaticAuthorizationConfig;
use IntegrationEngine\Core\Exception\DynamicAuthException;
use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Tests\Fake\FakeProtectedAction;
use IntegrationEngine\Tests\Fake\FakeTokenAction;
use PHPUnit\Framework\Attributes\Test;

final class DynamicAuthSadPathTest extends IntegrationEngineTestCase
{
    // ── Invalid token response ────────────────────────────────────────────────

    #[Test]
    public function dynamicAuthThrowsWhenTokenFieldMissing(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->client->setResponse(FakeTokenAction::getName(), ['wrong_field' => 'token']);

        $this->expectException(DynamicAuthException::class);
        $this->expectExceptionMessageMatches('/does not contain field/');

        $this->engine->send(FakeProtectedAction::getName());
    }

    #[Test]
    public function nonScalarTokenFieldThrows(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => ['nested' => 'value']]);
        $this->client->setResponse(FakeProtectedAction::getName(), []);

        $this->expectException(DynamicAuthException::class);
        $this->expectExceptionMessage('Token field "access_token" must be a scalar value.');

        $this->engine->send(FakeProtectedAction::getName());
    }

    // ── 401 retry ─────────────────────────────────────────────────────────────

    #[Test]
    public function rejectedCachedTokenIsDroppedAndRequestRetriedWithFreshToken(): void
    {
        $cacheKey = 'integration_engine.token.test_integration.'.FakeTokenAction::getName();
        $this->cache->set($cacheKey, 'stale_token', 60);

        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'fresh_token']);
        $this->client->setResponse(FakeProtectedAction::getName(), []);
        $this->client->queueException(FakeProtectedAction::getName(), new RequestResponseException(statusCode: 401, context: 'token revoked'));

        $this->engine->send(FakeProtectedAction::getName());

        self::assertSame(2, $this->client->callCount(FakeProtectedAction::getName()));
        self::assertSame(1, $this->client->callCount(FakeTokenAction::getName()));

        $auth = $this->client->lastAction()?->getAuthorization();
        self::assertInstanceOf(StaticAuthorizationConfig::class, $auth);
        self::assertSame('fresh_token', $auth->params['token']);
        self::assertSame('fresh_token', $this->cache->get($cacheKey));
    }

    #[Test]
    public function freshTokenRejectedWith401IsNotRetried(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'bad_token']);
        $this->client->queueException(FakeProtectedAction::getName(), new RequestResponseException(statusCode: 401, context: 'invalid token'));

        try {
            $this->engine->send(FakeProtectedAction::getName());
            self::fail('Expected RequestResponseException');
        } catch (RequestResponseException $e) {
            self::assertSame(401, $e->statusCode);
        }

        // The token was just fetched — retrying would fetch the same one.
        self::assertSame(1, $this->client->callCount(FakeProtectedAction::getName()));
        self::assertSame(1, $this->client->callCount(FakeTokenAction::getName()));
    }

    #[Test]
    public function non401ErrorWithCachedTokenIsNotRetried(): void
    {
        $cacheKey = 'integration_engine.token.test_integration.'.FakeTokenAction::getName();
        $this->cache->set($cacheKey, 'valid_token', 60);

        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->client->queueException(FakeProtectedAction::getName(), new RequestResponseException(statusCode: 500, context: 'server error'));

        try {
            $this->engine->send(FakeProtectedAction::getName());
            self::fail('Expected RequestResponseException');
        } catch (RequestResponseException $e) {
            self::assertSame(500, $e->statusCode);
        }

        self::assertSame(1, $this->client->callCount(FakeProtectedAction::getName()));
        // The token must survive: a 500 says nothing about its validity.
        self::assertSame('valid_token', $this->cache->get($cacheKey));
    }

    #[Test]
    public function second401AfterRetryPropagates(): void
    {
        $cacheKey = 'integration_engine.token.test_integration.'.FakeTokenAction::getName();
        $this->cache->set($cacheKey, 'stale_token', 60);

        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'fresh_token']);
        $this->client->queueException(FakeProtectedAction::getName(), new RequestResponseException(statusCode: 401, context: 'token revoked'));
        $this->client->queueException(FakeProtectedAction::getName(), new RequestResponseException(statusCode: 401, context: 'still unauthorized'));

        try {
            $this->engine->send(FakeProtectedAction::getName());
            self::fail('Expected RequestResponseException');
        } catch (RequestResponseException $e) {
            self::assertSame(401, $e->statusCode);
        }

        // Exactly one retry: original call + one with the fresh token.
        self::assertSame(2, $this->client->callCount(FakeProtectedAction::getName()));
        self::assertSame(1, $this->client->callCount(FakeTokenAction::getName()));
    }
}
