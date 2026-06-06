<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\AbstractMapper;
use IntegrationEngine\Core\Contract\ActionBodyInterface;
use IntegrationEngine\Core\Contract\ActionContextInterface;
use IntegrationEngine\Core\Contract\ClientInterface;
use IntegrationEngine\Core\Contract\RequestHeadersInterface;
use IntegrationEngine\Core\Contract\ResponseInterface;
use IntegrationEngine\Core\Exception\ActionNotFoundException;
use IntegrationEngine\Core\Exception\MapperActionMismatchException;
use IntegrationEngine\Core\Exception\NotMappedActionException;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Core\Port\CachePort;
use IntegrationEngine\Core\Port\ConfigPort;
use IntegrationEngine\Core\Response\EmptyResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EngineContractTest extends TestCase
{
    private IntegrationEngine $engine;
    private EngFakeConfigPort $config;
    private EngFakeClient $client;
    private EngFakeCache $cache;

    protected function setUp(): void
    {
        $this->config = new EngFakeConfigPort();
        $this->client = new EngFakeClient();
        $this->cache = new EngFakeCache();

        $this->engine = new IntegrationEngine(
            config: $this->config,
            client: $this->client,
            cache: $this->cache,
        );
    }

    // ── Flujo completo ────────────────────────────────────────────────────────

    #[Test]
    public function engineExecutesFullFlowAndReturnsMappedResponse(): void
    {
        $this->config->register(EngBasicAction::getName(), EngBasicAction::create('GET', '/items'));
        $this->client->setResponse(EngBasicAction::getName(), ['id' => 1, 'name' => 'item-one']);

        $response = $this->engine->send(EngBasicAction::getName());

        self::assertInstanceOf(EngBasicResponse::class, $response);
        self::assertSame(['id' => 1, 'name' => 'item-one'], $response->toArray());
    }

    #[Test]
    public function mapperReceivesRawResponseAndBuildsTypedObject(): void
    {
        $this->config->register(EngBasicAction::getName(), EngBasicAction::create('GET', '/items'));
        $this->client->setResponse(EngBasicAction::getName(), ['id' => 42, 'name' => 'widget']);

        $response = $this->engine->send(EngBasicAction::getName());

        self::assertInstanceOf(EngBasicResponse::class, $response);
        self::assertSame(42, $response->toArray()['id']);
        self::assertSame('widget', $response->toArray()['name']);
    }

    // ── Acción sin response ───────────────────────────────────────────────────

    #[Test]
    public function actionWithNoResponseReturnsEmptyResponse(): void
    {
        $this->config->register(EngNoResponseAction::getName(), EngNoResponseAction::create('DELETE', '/items/1'));

        $response = $this->engine->send(EngNoResponseAction::getName());

        self::assertInstanceOf(EmptyResponse::class, $response);
        self::assertSame([], $response->toArray());
    }

    #[Test]
    public function actionWithNoResponseStillCallsClient(): void
    {
        // El engine llama al cliente antes de comprobar hasResponse
        $this->config->register(EngNoResponseAction::getName(), EngNoResponseAction::create('DELETE', '/items/1'));
        $this->client->setResponse(EngNoResponseAction::getName(), []);

        $this->engine->send(EngNoResponseAction::getName());

        self::assertSame(1, $this->client->callCount(EngNoResponseAction::getName()));
    }

    // ── Excepciones del contrato ──────────────────────────────────────────────

    #[Test]
    public function unknownActionNameThrowsActionNotFoundException(): void
    {
        $this->expectException(ActionNotFoundException::class);

        $this->engine->send('non_existent_action');
    }

    #[Test]
    public function actionWithResponseButNoMapperThrows(): void
    {
        $this->config->register(EngMissingMapperAction::getName(), EngMissingMapperAction::create('GET', '/items'));
        $this->client->setResponse(EngMissingMapperAction::getName(), []);

        $this->expectException(NotMappedActionException::class);

        $this->engine->send(EngMissingMapperAction::getName());
    }

    #[Test]
    public function mapperPointingToWrongActionThrows(): void
    {
        $this->config->register(EngMismatchAction::getName(), EngMismatchAction::create('GET', '/items'));
        $this->client->setResponse(EngMismatchAction::getName(), []);

        $this->expectException(MapperActionMismatchException::class);

        $this->engine->send(EngMismatchAction::getName());
    }

    // ── Contexto llega al cliente ─────────────────────────────────────────────

    #[Test]
    public function contextIsPassedThroughToClient(): void
    {
        $this->config->register(EngBasicAction::getName(), EngBasicAction::create('GET', '/items/{id}'));
        $this->client->setResponse(EngBasicAction::getName(), []);

        $context = new class implements ActionContextInterface {
            public static function create(array $data): self
            {
                return new self();
            }

            public function toArray(): array
            {
                return ['id' => '7'];
            }
        };

        $this->engine->send(EngBasicAction::getName(), $context);

        self::assertSame(['id' => '7'], $this->client->lastContext()?->toArray());
    }
}

// ──────────────────────────────────────────────
// Fakes
// ──────────────────────────────────────────────

final class EngFakeCache implements CachePort
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->data);
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        $this->data[$key] = $value;
    }
}

final class EngFakeClient implements ClientInterface
{
    /** @var array<string, array<mixed>> */
    private array $responses = [];

    /** @var array<string, int> */
    private array $calls = [];
    private ?ActionContextInterface $lastContext = null;

    /** @param array<mixed> $response */
    public function setResponse(string $name, array $response): void
    {
        $this->responses[$name] = $response;
    }

    public function callCount(string $name): int
    {
        return $this->calls[$name] ?? 0;
    }

    public function lastContext(): ?ActionContextInterface
    {
        return $this->lastContext;
    }

    /** @return array<mixed> */
    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
        $this->calls[$action::getName()] = ($this->calls[$action::getName()] ?? 0) + 1;
        $this->lastContext = $context;

        return $this->responses[$action::getName()] ?? [];
    }
}

final class EngFakeConfigPort implements ConfigPort
{
    /** @var array<string, AbstractAction> */
    private array $actions = [];

    public function register(string $name, AbstractAction $action): void
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
// Fixtures
// ──────────────────────────────────────────────

final class EngBasicResponse implements ResponseInterface
{
    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }
}

final class EngBasicMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return EngBasicAction::class;
    }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return new EngBasicResponse($response);
    }
}

final class EngBasicAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'eng_basic';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): string
    {
        return EngBasicMapper::class;
    }
}

final class EngNoResponseAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'eng_no_response';
    }

    public static function hasResponse(): bool
    {
        return false;
    }

    public static function mapper(): ?string
    {
        return null;
    }
}

final class EngMissingMapperAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'eng_missing_mapper';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): ?string
    {
        return null;
    }
}

final class EngMismatchMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return EngBasicAction::class;
    } // deliberadamente incorrecto

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return new EngBasicResponse($response);
    }
}

final class EngMismatchAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'eng_mismatch';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): string
    {
        return EngMismatchMapper::class;
    }
}
