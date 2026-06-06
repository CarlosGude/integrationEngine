<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\AbstractMapper;
use IntegrationEngine\Core\Contract\ActionContextInterface;
use IntegrationEngine\Core\Contract\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Contract\ResponseInterface;
use IntegrationEngine\Core\Contract\StaticAuthorizationConfig;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Tests\Fake\FakeCache;
use IntegrationEngine\Tests\Fake\FakeClient;
use IntegrationEngine\Tests\Fake\FakeConfigPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DynamicAuthTest extends TestCase
{
    private IntegrationEngine $engine;
    private FakeConfigPort $config;
    private FakeClient $client;
    private FakeCache $cache;

    protected function setUp(): void
    {
        $this->config = new FakeConfigPort();
        $this->client = new FakeClient();
        $this->cache = new FakeCache();

        $this->engine = new IntegrationEngine(
            config: $this->config,
            client: $this->client,
            cache: $this->cache,
        );
    }

    #[Test]
    public function dynamicAuthResolvesTokenAndSetsStaticAuth(): void
    {
        $this->config->register(DynTokenAction::getName(), DynTokenAction::create('GET', '/token'));
        $this->config->register(DynProtectedAction::getName(), DynProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: DynTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->client->setResponse(DynTokenAction::getName(), ['access_token' => 'resolved_token']);
        $this->client->setResponse(DynProtectedAction::getName(), []);

        $this->engine->send(DynProtectedAction::getName());

        $auth = $this->client->lastAction()?->getAuthorization();
        self::assertInstanceOf(StaticAuthorizationConfig::class, $auth);
        self::assertSame('bearer', $auth->type);
        self::assertSame('resolved_token', $auth->params['token']);
    }

    #[Test]
    public function dynamicAuthUsesApiKeyForCustomHeader(): void
    {
        $this->config->register(DynTokenAction::getName(), DynTokenAction::create('GET', '/token'));
        $this->config->register(DynProtectedAction::getName(), DynProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: DynTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
            header: 'X-Custom-Token',
        )));
        $this->client->setResponse(DynTokenAction::getName(), ['access_token' => 'my_token']);
        $this->client->setResponse(DynProtectedAction::getName(), []);

        $this->engine->send(DynProtectedAction::getName());

        $auth = $this->client->lastAction()?->getAuthorization();
        self::assertInstanceOf(StaticAuthorizationConfig::class, $auth);
        self::assertSame('api_key', $auth->type);
        self::assertSame('X-Custom-Token', $auth->params['header']);
    }

    #[Test]
    public function dynamicAuthCachesTokenOnFirstCall(): void
    {
        $this->config->register(DynTokenAction::getName(), DynTokenAction::create('GET', '/token'));
        $this->config->register(DynProtectedAction::getName(), DynProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: DynTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->client->setResponse(DynTokenAction::getName(), ['access_token' => 'cached_token']);
        $this->client->setResponse(DynProtectedAction::getName(), []);

        $this->engine->send(DynProtectedAction::getName());
        $this->engine->send(DynProtectedAction::getName());

        self::assertTrue($this->cache->has('integration_engine.token.'.DynTokenAction::getName()));
        self::assertSame(1, $this->client->callCount(DynTokenAction::getName()));
    }

    #[Test]
    public function dynamicAuthThrowsWhenTokenFieldMissing(): void
    {
        $this->config->register(DynTokenAction::getName(), DynTokenAction::create('GET', '/token'));
        $this->config->register(DynProtectedAction::getName(), DynProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: DynTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->client->setResponse(DynTokenAction::getName(), ['wrong_field' => 'token']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/does not contain field/');

        $this->engine->send(DynProtectedAction::getName());
    }

    #[Test]
    public function dynamicAuthUsesTokenFromCacheWhenAvailable(): void
    {
        $this->cache->set('integration_engine.token.'.DynTokenAction::getName(), 'pre_cached_token', 60);

        $this->config->register(DynProtectedAction::getName(), DynProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: DynTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->client->setResponse(DynProtectedAction::getName(), []);

        $this->engine->send(DynProtectedAction::getName());

        $auth = $this->client->lastAction()?->getAuthorization();
        self::assertInstanceOf(StaticAuthorizationConfig::class, $auth);
        self::assertSame('pre_cached_token', $auth->params['token']);
    }

    /**
     * Regression: context must reach the client even when dynamic auth
     * reconstructs the action. The client receives the context directly
     * from the engine — the action no longer stores it.
     */
    #[Test]
    public function contextReachesClientAfterDynamicAuthReconstruction(): void
    {
        $this->config->register(DynTokenAction::getName(), DynTokenAction::create('GET', '/token'));
        $this->config->register(DynPathAction::getName(), DynPathAction::create('GET', '/orders/{id}', null, new DynamicAuthorizationConfig(
            action: DynTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->client->setResponse(DynTokenAction::getName(), ['access_token' => 'token_xyz']);
        $this->client->setResponse(DynPathAction::getName(), []);

        $context = new class implements ActionContextInterface {
            public static function create(array $data): self
            {
                return new self();
            }

            public function toArray(): array
            {
                return ['id' => '99'];
            }
        };

        $this->engine->send(DynPathAction::getName(), $context);

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
        $this->config->register(DynPathAction::getName(), DynPathAction::create('GET', '/orders/{id}'));
        $this->client->setResponse(DynPathAction::getName(), []);

        $ctx1 = new class implements ActionContextInterface {
            public static function create(array $data): self
            {
                return new self();
            }

            public function toArray(): array
            {
                return ['id' => '1'];
            }
        };
        $ctx2 = new class implements ActionContextInterface {
            public static function create(array $data): self
            {
                return new self();
            }

            public function toArray(): array
            {
                return ['id' => '2'];
            }
        };

        $this->engine->send(DynPathAction::getName(), $ctx1);
        $receivedCtx1 = $this->client->lastContext();
        self::assertNotNull($receivedCtx1);
        self::assertSame(['id' => '1'], $receivedCtx1->toArray());

        $this->engine->send(DynPathAction::getName(), $ctx2);
        $receivedCtx2 = $this->client->lastContext();
        self::assertNotNull($receivedCtx2);
        self::assertSame(['id' => '2'], $receivedCtx2->toArray());
    }
}

// ──────────────────────────────────────────────
// Action + mapper fixtures
// ──────────────────────────────────────────────

final class DynTokenResponse implements ResponseInterface
{
    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }
}

final class DynTokenMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return DynTokenAction::class;
    }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        /** @var array<string, mixed> $response */
        return new DynTokenResponse($response);
    }
}

final class DynTokenAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'dyn_fetch_token';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): string
    {
        return DynTokenMapper::class;
    }
}

final class DynProtectedResponse implements ResponseInterface
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [];
    }
}

final class DynProtectedMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return DynProtectedAction::class;
    }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return new DynProtectedResponse();
    }
}

final class DynProtectedAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'dyn_get_protected';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): string
    {
        return DynProtectedMapper::class;
    }
}

final class DynPathResponse implements ResponseInterface
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [];
    }
}

final class DynPathMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return DynPathAction::class;
    }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return new DynPathResponse();
    }
}

final class DynPathAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'dyn_path_action';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): string
    {
        return DynPathMapper::class;
    }
}
