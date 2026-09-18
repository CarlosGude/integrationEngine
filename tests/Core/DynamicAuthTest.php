<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Contract\Auth\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Contract\Auth\StaticAuthorizationConfig;
use IntegrationEngine\Core\Dispatch\AuthenticationHandler;
use IntegrationEngine\Core\IntegrationEngine;
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

    /**
     * A token action with no declared response/mapper is valid — the raw
     * response array is used directly to read the token field.
     */
    #[Test]
    public function dynamicAuthResolvesTokenFromActionWithoutResponseMapper(): void
    {
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/token'));
        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakePathAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->client->setResponse(FakePathAction::getName(), ['access_token' => 'raw_token']);
        $this->client->setResponse(FakeProtectedAction::getName(), []);

        $this->engine->send(FakeProtectedAction::getName());

        $auth = $this->client->lastAction()?->getAuthorization();
        self::assertInstanceOf(StaticAuthorizationConfig::class, $auth);
        self::assertSame('raw_token', $auth->params['token']);
    }

    /**
     * Regression: the bundle's compiler pass builds a DynamicAuthHandler as a
     * separate service and injects it into IntegrationEngine's constructor
     * (see IntegrationCompilerPass::registerIntegration()). If that seam were
     * ever ignored in favour of always building a fresh handler internally,
     * every test in this suite would still pass — none of them pass an
     * authHandler — while the real DI-wired path would silently regress.
     */
    #[Test]
    public function injectedAuthHandlerIsUsedInsteadOfBuildingANewOne(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'injected_token']);
        $this->client->setResponse(FakeProtectedAction::getName(), []);

        $injectedAuthHandler = new AuthenticationHandler($this->config, $this->client, $this->cache, 'injected_integration');

        $engine = new IntegrationEngine(
            config: $this->config,
            client: $this->client,
            cache: $this->cache,
            integrationName: 'test_integration',
            authHandler: $injectedAuthHandler,
        );

        $engine->send(FakeProtectedAction::getName());

        // The token is cached under the *injected* handler's integration name,
        // proving the constructor used it instead of building its own.
        self::assertSame(
            'injected_token',
            $this->cache->get('integration_engine.token.injected_integration.'.FakeTokenAction::getName().'.'.sha1('')),
        );
        self::assertNull(
            $this->cache->get('integration_engine.token.test_integration.'.FakeTokenAction::getName().'.'.sha1('')),
        );
    }

    /**
     * A token action with no declared response/mapper must expose the
     * whole raw body untouched — not just its first entry — so the token
     * field can be found regardless of its position in the payload.
     */
    #[Test]
    public function dynamicAuthFindsTokenFieldAnywhereInAMultiKeyRawBody(): void
    {
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/token'));
        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakePathAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->client->setResponse(FakePathAction::getName(), ['expires_in' => 3600, 'access_token' => 'raw_token']);
        $this->client->setResponse(FakeProtectedAction::getName(), []);

        $this->engine->send(FakeProtectedAction::getName());

        $auth = $this->client->lastAction()?->getAuthorization();
        self::assertInstanceOf(StaticAuthorizationConfig::class, $auth);
        self::assertSame('raw_token', $auth->params['token']);
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
    public function dynamicAuthUsesTokenFromCacheWhenAvailable(): void
    {
        $this->cache->set('integration_engine.token.test_integration.'.FakeTokenAction::getName().'.'.sha1(''), 'pre_cached_token', 60);

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

    /**
     * The $client parameter handle() receives (the resolved, per-call
     * client for the request's baseUrl) must be used for both the token
     * fetch and the protected call — never silently replaced by the
     * handler's own default client.
     */
    #[Test]
    public function tokenFetchAndProtectedCallUseTheResolvedClientForTheCurrentBaseUrl(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'tok']);
        $this->client->setResponse(FakeProtectedAction::getName(), []);

        $this->engine->send(FakeProtectedAction::getName(), baseUrl: 'https://tenant-a.example.com');

        self::assertSame('https://tenant-a.example.com', $this->client->baseUrlUsedFor(FakeTokenAction::getName()));
        self::assertSame('https://tenant-a.example.com', $this->client->baseUrlUsedFor(FakeProtectedAction::getName()));
    }

    /**
     * Multi-connection requirement: the same integration serving several
     * connections through a per-call baseUrl must never let one
     * connection's token leak into another's cache entry, and a repeat
     * call for a connection must reuse only that connection's token.
     */
    #[Test]
    public function differentBaseUrlsGetIsolatedTokenCacheEntries(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->client->setResponse(FakeProtectedAction::getName(), []);

        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'token_a']);
        $this->engine->send(FakeProtectedAction::getName(), baseUrl: 'https://tenant-a.example.com');

        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'token_b']);
        $this->engine->send(FakeProtectedAction::getName(), baseUrl: 'https://tenant-b.example.com');

        self::assertSame(
            'token_a',
            $this->cache->get('integration_engine.token.test_integration.'.FakeTokenAction::getName().'.'.sha1('https://tenant-a.example.com')),
        );
        self::assertSame(
            'token_b',
            $this->cache->get('integration_engine.token.test_integration.'.FakeTokenAction::getName().'.'.sha1('https://tenant-b.example.com')),
        );

        // A repeat call for tenant A must reuse tenant A's own cached
        // token — no refetch, and definitely not tenant B's token.
        $callsBeforeReuse = $this->client->callCount(FakeTokenAction::getName());
        $this->engine->send(FakeProtectedAction::getName(), baseUrl: 'https://tenant-a.example.com');

        self::assertSame($callsBeforeReuse, $this->client->callCount(FakeTokenAction::getName()));
        $auth = $this->client->lastAction()?->getAuthorization();
        self::assertInstanceOf(StaticAuthorizationConfig::class, $auth);
        self::assertSame('token_a', $auth->params['token']);
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
