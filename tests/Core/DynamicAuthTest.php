<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\AbstractMapper;
use IntegrationEngine\Core\Contract\ActionBodyInterface;
use IntegrationEngine\Core\Contract\ActionContextInterface;
use IntegrationEngine\Core\Contract\ClientInterface;
use IntegrationEngine\Core\Contract\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Contract\ResponseInterface;
use IntegrationEngine\Core\Contract\StaticAuthorizationConfig;
use IntegrationEngine\Core\Exception\ActionNotFoundException;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Core\Port\CachePort;
use IntegrationEngine\Core\Port\ConfigPort;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class DynamicAuthTest extends TestCase
{
    private IntegrationEngine $engine;
    private DynFakeConfigPort $config;
    private DynFakeClient $client;
    private DynFakeCache $cache;

    protected function setUp(): void
    {
        $this->config = new DynFakeConfigPort();
        $this->client = new DynFakeClient();
        $this->cache = new DynFakeCache();

        $this->engine = new IntegrationEngine(
            config: $this->config,
            client: $this->client,
            cache: $this->cache,
        );
    }

    #[Test]
    public function dynamicAuthResolvesTokenAndSetsStaticAuth(): void
    {
        $tokenAction = DynTokenAction::create('GET', '/token');
        $protectedAction = DynProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: DynTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        ));

        $this->config->setAction(DynTokenAction::getName(), $tokenAction);
        $this->config->setAction(DynProtectedAction::getName(), $protectedAction);
        $this->client->setResponse(DynTokenAction::getName(), ['access_token' => 'resolved_token']);
        $this->client->setResponse(DynProtectedAction::getName(), []);

        $this->engine->send(DynProtectedAction::getName());

        $lastAction = $this->client->lastAction();
        self::assertNotNull($lastAction);

        $auth = $lastAction->getAuthorization();
        self::assertInstanceOf(StaticAuthorizationConfig::class, $auth);
        self::assertSame('bearer', $auth->type);
        self::assertSame('resolved_token', $auth->params['token']);
    }

    #[Test]
    public function dynamicAuthUsesApiKeyForCustomHeader(): void
    {
        $tokenAction = DynTokenAction::create('GET', '/token');
        $protectedAction = DynProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: DynTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
            header: 'X-Custom-Token',
        ));

        $this->config->setAction(DynTokenAction::getName(), $tokenAction);
        $this->config->setAction(DynProtectedAction::getName(), $protectedAction);
        $this->client->setResponse(DynTokenAction::getName(), ['access_token' => 'my_token']);
        $this->client->setResponse(DynProtectedAction::getName(), []);

        $this->engine->send(DynProtectedAction::getName());

        $lastAction = $this->client->lastAction();
        self::assertNotNull($lastAction);

        $auth = $lastAction->getAuthorization();
        self::assertInstanceOf(StaticAuthorizationConfig::class, $auth);
        self::assertSame('api_key', $auth->type);
        self::assertSame('X-Custom-Token', $auth->params['header']);
    }

    #[Test]
    public function dynamicAuthCachesTokenOnFirstCall(): void
    {
        $tokenAction = DynTokenAction::create('GET', '/token');
        $protectedAction = DynProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: DynTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        ));

        $this->config->setAction(DynTokenAction::getName(), $tokenAction);
        $this->config->setAction(DynProtectedAction::getName(), $protectedAction);
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
        $tokenAction = DynTokenAction::create('GET', '/token');
        $protectedAction = DynProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: DynTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        ));

        $this->config->setAction(DynTokenAction::getName(), $tokenAction);
        $this->config->setAction(DynProtectedAction::getName(), $protectedAction);
        $this->client->setResponse(DynTokenAction::getName(), ['wrong_field' => 'token']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/does not contain field/');

        $this->engine->send(DynProtectedAction::getName());
    }

    #[Test]
    public function dynamicAuthUsesTokenFromCacheWhenAvailable(): void
    {
        $this->cache->set('integration_engine.token.'.DynTokenAction::getName(), 'pre_cached_token', 60);

        $protectedAction = DynProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: DynTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        ));

        $this->config->setAction(DynProtectedAction::getName(), $protectedAction);
        $this->client->setResponse(DynProtectedAction::getName(), []);

        $this->engine->send(DynProtectedAction::getName());

        $lastAction = $this->client->lastAction();
        self::assertNotNull($lastAction);

        $auth = $lastAction->getAuthorization();
        self::assertInstanceOf(StaticAuthorizationConfig::class, $auth);
        self::assertSame('pre_cached_token', $auth->params['token']);
    }

    /**
     * Regression test for the bug where applyAuthorization() called ->withContext(null)
     * unconditionally, losing the context that applyContext() had already applied.
     *
     * Scenario: action with {id} placeholder in path + dynamic auth simultaneously.
     * The path must resolve correctly even after auth reconstruction.
     */
    #[Test]
    public function contextIsPreservedAfterDynamicAuthReconstruction(): void
    {
        $tokenAction = DynTokenAction::create('GET', '/token');
        $protectedAction = DynPathAction::create('GET', '/orders/{id}', null, new DynamicAuthorizationConfig(
            action: DynTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        ));

        $this->config->setAction(DynTokenAction::getName(), $tokenAction);
        $this->config->setAction(DynPathAction::getName(), $protectedAction);
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

        $lastAction = $this->client->lastAction();
        self::assertNotNull($lastAction);

        // Path must be resolved with the context even after dynamic auth reconstruction
        self::assertSame('/orders/99', $lastAction->getPath());

        // Auth must also be set correctly
        $auth = $lastAction->getAuthorization();
        self::assertInstanceOf(StaticAuthorizationConfig::class, $auth);
        self::assertSame('token_xyz', $auth->params['token']);
    }
}

// ──────────────────────────────────────────────
// Inline fakes
// ──────────────────────────────────────────────

final class DynFakeCache implements CachePort
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->data);
    }
}

final class DynFakeClient implements ClientInterface
{
    /** @var array<string, array<mixed>> */
    private array $responses = [];
    private ?AbstractAction $last = null;

    /** @var array<string, int> */
    private array $callCount = [];

    /** @param array<mixed> $response */
    public function setResponse(string $name, array $response): void
    {
        $this->responses[$name] = $response;
    }

    public function lastAction(): ?AbstractAction
    {
        return $this->last;
    }

    public function callCount(string $name): int
    {
        return $this->callCount[$name] ?? 0;
    }

    /** @return array<mixed> */
    public function send(AbstractAction $action): array
    {
        $this->last = $action;
        $this->callCount[$action::getName()] = ($this->callCount[$action::getName()] ?? 0) + 1;

        return $this->responses[$action::getName()] ?? [];
    }
}

final class DynFakeConfigPort implements ConfigPort
{
    /** @var array<string, AbstractAction> */
    private array $actions = [];

    public function setAction(string $name, AbstractAction $action): void
    {
        $this->actions[$name] = $action;
    }

    public function getAction(string $name, ?ActionBodyInterface $bodyData = null): AbstractAction
    {
        if (!isset($this->actions[$name])) {
            throw new ActionNotFoundException($name);
        }

        return $this->actions[$name];
    }
}

// ──────────────────────────────────────────────
// Inline action + mapper fixtures
// ──────────────────────────────────────────────

final class DynTokenResponse implements ResponseInterface
{
    /** @param array<mixed> $data */
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

    public static function mapper(): ?string
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

    public static function mapper(): ?string
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

    public static function mapper(): ?string
    {
        return DynPathMapper::class;
    }
}
