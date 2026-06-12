<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Contract\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Contract\StaticAuthorizationConfig;
use IntegrationEngine\Core\Exception\DynamicAuthException;
use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Tests\Fake\FakeContext;
use IntegrationEngine\Tests\Fake\FakePathAction;
use IntegrationEngine\Tests\Fake\FakeProtectedAction;
use IntegrationEngine\Tests\Fake\FakeTokenAction;
use PHPUnit\Framework\Attributes\Test;

final class DynamicAuthTest extends IntegrationEngineTestCase
{
    #[Test]
    public function dynamicAuthResolvesTokenAndSetsStaticAuth(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'resolved_token']);
        $this->client->setResponse(FakeProtectedAction::getName(), []);

        $this->engine->send(FakeProtectedAction::getName());

        $auth = $this->client->lastAction()?->getAuthorization();
        self::assertInstanceOf(StaticAuthorizationConfig::class, $auth);
        self::assertSame('bearer', $auth->type);
        self::assertSame('resolved_token', $auth->params['token']);
    }

    #[Test]
    public function dynamicAuthCastsIntegerTokenToString(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 42]);
        $this->client->setResponse(FakeProtectedAction::getName(), []);

        $this->engine->send(FakeProtectedAction::getName());

        $auth = $this->client->lastAction()?->getAuthorization();
        self::assertInstanceOf(StaticAuthorizationConfig::class, $auth);
        self::assertSame('42', $auth->params['token']);
    }

    #[Test]
    public function dynamicAuthUsesCustomPrefixInAuthorizationHeader(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
            prefix: 'Token',
        )));
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'my_token']);
        $this->client->setResponse(FakeProtectedAction::getName(), []);

        $this->engine->send(FakeProtectedAction::getName());

        $auth = $this->client->lastAction()?->getAuthorization();
        self::assertInstanceOf(StaticAuthorizationConfig::class, $auth);
        self::assertSame('bearer', $auth->type);
        self::assertSame('my_token', $auth->params['token']);
        self::assertSame('Token', $auth->params['prefix']);
    }

    #[Test]
    public function dynamicAuthUsesApiKeyForCustomHeader(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
            header: 'X-Custom-Token',
        )));
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'my_token']);
        $this->client->setResponse(FakeProtectedAction::getName(), []);

        $this->engine->send(FakeProtectedAction::getName());

        $auth = $this->client->lastAction()?->getAuthorization();
        self::assertInstanceOf(StaticAuthorizationConfig::class, $auth);
        self::assertSame('api_key', $auth->type);
        self::assertSame('X-Custom-Token', $auth->params['header']);
        // No explicit prefix on a custom header means a bare token.
        self::assertSame('', $auth->params['prefix']);
    }

    #[Test]
    public function dynamicAuthKeepsCustomPrefixOnCustomHeader(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
            header: 'X-Auth',
            prefix: 'Token',
        )));
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'my_token']);
        $this->client->setResponse(FakeProtectedAction::getName(), []);

        $this->engine->send(FakeProtectedAction::getName());

        $auth = $this->client->lastAction()?->getAuthorization();
        self::assertInstanceOf(StaticAuthorizationConfig::class, $auth);
        self::assertSame('api_key', $auth->type);
        self::assertSame('X-Auth', $auth->params['header']);
        self::assertSame('Token', $auth->params['prefix']);
    }

    #[Test]
    public function dynamicAuthDefaultsToBearerPrefixOnAuthorizationHeader(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'my_token']);
        $this->client->setResponse(FakeProtectedAction::getName(), []);

        $this->engine->send(FakeProtectedAction::getName());

        $auth = $this->client->lastAction()?->getAuthorization();
        self::assertInstanceOf(StaticAuthorizationConfig::class, $auth);
        self::assertSame('Bearer', $auth->params['prefix']);
    }

    #[Test]
    public function dynamicAuthCachesTokenOnFirstCall(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'cached_token']);
        $this->client->setResponse(FakeProtectedAction::getName(), []);

        $this->engine->send(FakeProtectedAction::getName());
        $this->engine->send(FakeProtectedAction::getName());

        // The token action must be called only once — the second send() hits the cache.
        self::assertSame(1, $this->client->callCount(FakeTokenAction::getName()));
    }

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
    public function dynamicAuthUsesTokenFromCacheWhenAvailable(): void
    {
        $this->cache->set('integration_engine.token.test_integration.'.FakeTokenAction::getName(), 'pre_cached_token', 60);

        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->client->setResponse(FakeProtectedAction::getName(), []);

        $this->engine->send(FakeProtectedAction::getName());

        $auth = $this->client->lastAction()?->getAuthorization();
        self::assertInstanceOf(StaticAuthorizationConfig::class, $auth);
        self::assertSame('pre_cached_token', $auth->params['token']);
    }

    // ── 401 retry with fresh token ───────────────────────────────────────────

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

    /**
     * Regression: context must reach the client even when dynamic auth
     * reconstructs the action. The client receives the context directly
     * from the engine — the action no longer stores it.
     */
    #[Test]
    public function contextReachesClientAfterDynamicAuthReconstruction(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/orders/{id}', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'token_xyz']);
        $this->client->setResponse(FakePathAction::getName(), []);

        $context = FakeContext::create(['id' => '99']);

        $this->engine->send(FakePathAction::getName(), $context);

        $receivedContext = $this->client->lastContext();
        self::assertNotNull($receivedContext);
        self::assertSame(['id' => '99'], $receivedContext->toArray());

        $auth = $this->client->lastAction()?->getAuthorization();
        self::assertInstanceOf(StaticAuthorizationConfig::class, $auth);
        self::assertSame('token_xyz', $auth->params['token']);
    }

    /**
     * The action must not store context — same instance resolves
     * different paths across multiple calls.
     */
    #[Test]
    public function actionRemainsStatelessAcrossMultipleSendCalls(): void
    {
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/orders/{id}'));
        $this->client->setResponse(FakePathAction::getName(), []);

        $ctx1 = FakeContext::create(['id' => '1']);
        $ctx2 = FakeContext::create(['id' => '2']);

        $this->engine->send(FakePathAction::getName(), $ctx1);
        $receivedCtx1 = $this->client->lastContext();
        self::assertNotNull($receivedCtx1);
        self::assertSame(['id' => '1'], $receivedCtx1->toArray());

        $this->engine->send(FakePathAction::getName(), $ctx2);
        $receivedCtx2 = $this->client->lastContext();
        self::assertNotNull($receivedCtx2);
        self::assertSame(['id' => '2'], $receivedCtx2->toArray());
    }
}
