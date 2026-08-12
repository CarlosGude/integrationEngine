<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Batch\EngineRequest;
use IntegrationEngine\Core\Contract\Auth\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Contract\Auth\StaticAuthorizationConfig;
use IntegrationEngine\Core\Contract\Connection\ConnectionCredentials;
use IntegrationEngine\Core\Exception\ConnectionResolutionException;
use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Tests\Fake\FakeConnectionResolver;
use IntegrationEngine\Tests\Fake\FakePathAction;
use IntegrationEngine\Tests\Fake\FakeProtectedAction;
use IntegrationEngine\Tests\Fake\FakeTokenAction;
use PHPUnit\Framework\Attributes\Test;

final class ConnectionResolutionTest extends IntegrationEngineTestCase
{
    private FakeConnectionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new FakeConnectionResolver();
        $this->engine = new IntegrationEngine(
            config: $this->config,
            client: $this->client,
            cache: $this->cache,
            integrationName: 'test_integration',
            connectionResolver: $this->resolver,
        );
    }

    // ── Basic resolution ─────────────────────────────────────────────────────

    #[Test]
    public function differentConnectionsResolveToDifferentCredentialsWithoutContamination(): void
    {
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/items'));
        $this->client->setResponse(FakePathAction::getName(), []);
        $this->resolver->register('conn_a', new ConnectionCredentials(baseUrl: 'https://tenant-a.example.com'));
        $this->resolver->register('conn_b', new ConnectionCredentials(baseUrl: 'https://tenant-b.example.com'));

        $this->engine->send(FakePathAction::getName(), connection: 'conn_a');
        self::assertSame('https://tenant-a.example.com', $this->client->lastBaseUrl());

        $this->engine->send(FakePathAction::getName(), connection: 'conn_b');
        self::assertSame('https://tenant-b.example.com', $this->client->lastBaseUrl());
    }

    #[Test]
    public function connectionAuthorizationOverridesTheActionsStaticAuthorization(): void
    {
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/items'));
        $this->client->setResponse(FakePathAction::getName(), []);
        $this->resolver->register('conn_a', new ConnectionCredentials(
            authorization: new StaticAuthorizationConfig('bearer', ['token' => 'token_a', 'prefix' => 'Bearer']),
        ));

        $this->engine->send(FakePathAction::getName(), connection: 'conn_a');

        $auth = $this->client->lastAction()?->getAuthorization();
        self::assertInstanceOf(StaticAuthorizationConfig::class, $auth);
        self::assertSame('token_a', $auth->params['token']);
    }

    #[Test]
    public function sendingWithoutAConnectionNeverInvokesTheResolver(): void
    {
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/items'));
        $this->client->setResponse(FakePathAction::getName(), []);

        // No credentials registered on $this->resolver for any connection —
        // if it were invoked with null, FakeConnectionResolver would throw.
        $this->engine->send(FakePathAction::getName());

        self::assertNull($this->client->lastBaseUrl());
    }

    #[Test]
    public function connectionWithoutAConfiguredResolverThrows(): void
    {
        $engine = new IntegrationEngine(
            config: $this->config,
            client: $this->client,
            cache: $this->cache,
            integrationName: 'test_integration',
        );
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/items'));

        $this->expectException(ConnectionResolutionException::class);
        $this->expectExceptionMessageMatches('/test_integration/');

        $engine->send(FakePathAction::getName(), connection: 'conn_a');
    }

    #[Test]
    public function explicitBaseUrlArgumentTakesPriorityOverResolvedCredentials(): void
    {
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/items'));
        $this->client->setResponse(FakePathAction::getName(), []);
        $this->resolver->register('conn_a', new ConnectionCredentials(baseUrl: 'https://resolved.example.com'));

        $this->engine->send(FakePathAction::getName(), baseUrl: 'https://explicit.example.com', connection: 'conn_a');

        self::assertSame('https://explicit.example.com', $this->client->lastBaseUrl());
    }

    // ── Dynamic auth cache isolation via connectionId ───────────────────────────

    /**
     * The scenario item 4's cache-key design was meant to cover: two
     * connections that resolve to the SAME base_url (a shared multi-tenant
     * endpoint) but different credentials must never share a cached token —
     * baseUrl alone can't discriminate them, only the resolved connectionId can.
     */
    #[Test]
    public function connectionsSharingABaseUrlGetIsolatedTokenCachesViaConnectionId(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $sharedUrl = 'https://shared.example.com';
        $this->resolver->register('tenant_a', new ConnectionCredentials(baseUrl: $sharedUrl, connectionId: 'tenant_a'));
        $this->resolver->register('tenant_b', new ConnectionCredentials(baseUrl: $sharedUrl, connectionId: 'tenant_b'));
        $this->client->setResponse(FakeProtectedAction::getName(), []);

        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'token_a']);
        $this->engine->send(FakeProtectedAction::getName(), connection: 'tenant_a');

        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'token_b']);
        $this->engine->send(FakeProtectedAction::getName(), connection: 'tenant_b');

        self::assertSame(
            'token_a',
            $this->cache->get('integration_engine.token.test_integration.'.FakeTokenAction::getName().'.'.sha1('tenant_a')),
        );
        self::assertSame(
            'token_b',
            $this->cache->get('integration_engine.token.test_integration.'.FakeTokenAction::getName().'.'.sha1('tenant_b')),
        );

        // A repeat call for tenant_a must reuse its own token, not tenant_b's.
        $callsBeforeReuse = $this->client->callCount(FakeTokenAction::getName());
        $this->engine->send(FakeProtectedAction::getName(), connection: 'tenant_a');

        self::assertSame($callsBeforeReuse, $this->client->callCount(FakeTokenAction::getName()));
        $auth = $this->client->lastAction()?->getAuthorization();
        self::assertInstanceOf(StaticAuthorizationConfig::class, $auth);
        self::assertSame('token_a', $auth->params['token']);
    }

    /**
     * Regression: ConnectionCredentials with only $authorization set (no
     * baseUrl, no connectionId) is an explicitly documented valid case —
     * "only $authorization if every connection hits the same URL". Without
     * a fallback, both connections' cache discriminator collapsed to null,
     * so tenant_b's call silently reused tenant_a's cached token.
     */
    #[Test]
    public function connectionsWithOnlyAuthorizationFallBackToTheConnectionValueItselfAsDiscriminator(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->resolver->register('tenant_a', new ConnectionCredentials());
        $this->resolver->register('tenant_b', new ConnectionCredentials());
        $this->client->setResponse(FakeProtectedAction::getName(), []);

        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'token_a']);
        $this->engine->send(FakeProtectedAction::getName(), connection: 'tenant_a');

        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'token_b']);
        $this->engine->send(FakeProtectedAction::getName(), connection: 'tenant_b');

        self::assertSame(
            'token_a',
            $this->cache->get('integration_engine.token.test_integration.'.FakeTokenAction::getName().'.'.sha1('tenant_a')),
        );
        self::assertSame(
            'token_b',
            $this->cache->get('integration_engine.token.test_integration.'.FakeTokenAction::getName().'.'.sha1('tenant_b')),
        );

        $auth = $this->client->lastAction()?->getAuthorization();
        self::assertInstanceOf(StaticAuthorizationConfig::class, $auth);
        self::assertSame('token_b', $auth->params['token']);
    }

    // ── Batch ────────────────────────────────────────────────────────────────

    #[Test]
    public function sendManyResolvesEachItemsOwnConnection(): void
    {
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/items'));
        $this->client->setResponse(FakePathAction::getName(), []);
        $this->resolver->register('conn_a', new ConnectionCredentials(baseUrl: 'https://tenant-a.example.com'));
        $this->resolver->register('conn_b', new ConnectionCredentials(baseUrl: 'https://tenant-b.example.com'));

        $results = $this->engine->sendMany([
            'a' => new EngineRequest(FakePathAction::getName(), connection: 'conn_a'),
            'b' => new EngineRequest(FakePathAction::getName(), connection: 'conn_b'),
        ]);

        self::assertTrue($results['a']->isSuccess());
        self::assertTrue($results['b']->isSuccess());
    }

    /**
     * Efficiency regression: resolveConnection() must not call the
     * resolver once per batch item that shares the same $connection — a
     * natural pattern (many items for one tenant) would otherwise trigger
     * redundant resolver work (e.g. database round trips) per item.
     */
    #[Test]
    public function sendManyResolvesARepeatedConnectionOnlyOnce(): void
    {
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/items'));
        $this->client->setResponse(FakePathAction::getName(), []);
        $this->resolver->register('conn_a', new ConnectionCredentials(baseUrl: 'https://tenant-a.example.com'));

        $results = $this->engine->sendMany([
            'a' => new EngineRequest(FakePathAction::getName(), connection: 'conn_a'),
            'b' => new EngineRequest(FakePathAction::getName(), connection: 'conn_a'),
            'c' => new EngineRequest(FakePathAction::getName(), connection: 'conn_a'),
        ]);

        self::assertTrue($results['a']->isSuccess());
        self::assertTrue($results['b']->isSuccess());
        self::assertTrue($results['c']->isSuccess());
        self::assertSame(1, $this->resolver->callCount('conn_a'));
    }

    /**
     * Same isolation guarantee as the single-send test above, but inside one
     * sendMany() call and exercising the 401-retry path — proving
     * PreparedRequest's separate $cacheDiscriminator (not $baseUrl, which is
     * identical for both items here) drives the retry's cache lookup/delete.
     */
    #[Test]
    public function sendManyRetriesStaleTokenIsolatedByConnectionIdWhenBaseUrlIsShared(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $sharedUrl = 'https://shared.example.com';
        $this->resolver->register('tenant_a', new ConnectionCredentials(baseUrl: $sharedUrl, connectionId: 'tenant_a'));
        $staleCacheKey = 'integration_engine.token.test_integration.'.FakeTokenAction::getName().'.'.sha1('tenant_a');
        $this->cache->set($staleCacheKey, 'stale_token', 60);
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'fresh_token']);
        $this->client->setResponse(FakeProtectedAction::getName(), []);
        $this->client->queueException(FakeProtectedAction::getName(), new RequestResponseException(statusCode: 401, context: 'unauthorized'));

        $results = $this->engine->sendMany([
            'a' => new EngineRequest(FakeProtectedAction::getName(), connection: 'tenant_a'),
        ]);

        self::assertTrue($results['a']->isSuccess());
        self::assertSame('fresh_token', $this->cache->get($staleCacheKey));
    }
}
