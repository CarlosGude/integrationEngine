<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\AbstractMapper;
use IntegrationEngine\Core\Contract\ResponseInterface;
use IntegrationEngine\Core\Exception\ActionNotFoundException;
use IntegrationEngine\Core\Exception\MapperActionMismatchException;
use IntegrationEngine\Core\Exception\NotMappedActionException;
use IntegrationEngine\Core\Response\EmptyResponse;
use IntegrationEngine\Tests\Fake\FakeContext;
use IntegrationEngine\Tests\Fake\FakeTokenResponse;
use PHPUnit\Framework\Attributes\Test;

final class EngineContractTest extends IntegrationEngineTestCase
{
    // ── Flujo completo ────────────────────────────────────────────────────────

    #[Test]
    public function engineExecutesFullFlowAndReturnsMappedResponse(): void
    {
        $this->config->register(EngBasicAction::getName(), EngBasicAction::create('GET', '/items'));
        $this->client->setResponse(EngBasicAction::getName(), ['id' => 1, 'name' => 'item-one']);

        $response = $this->engine->send(EngBasicAction::getName());

        self::assertInstanceOf(FakeTokenResponse::class, $response);
        self::assertSame(['id' => 1, 'name' => 'item-one'], $response->toArray());
    }

    #[Test]
    public function mapperReceivesRawResponseAndBuildsTypedObject(): void
    {
        $this->config->register(EngBasicAction::getName(), EngBasicAction::create('GET', '/items'));
        $this->client->setResponse(EngBasicAction::getName(), ['id' => 42, 'name' => 'widget']);

        $response = $this->engine->send(EngBasicAction::getName());

        self::assertInstanceOf(FakeTokenResponse::class, $response);
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

        $context = FakeContext::create(['id' => '7']);

        $this->engine->send(EngBasicAction::getName(), $context);

        self::assertSame(['id' => '7'], $this->client->lastContext()?->toArray());
    }
}

// ──────────────────────────────────────────────
// Fixtures
// ──────────────────────────────────────────────

final class EngBasicMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return EngBasicAction::class;
    }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return new FakeTokenResponse($response);
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
        return new FakeTokenResponse($response);
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
