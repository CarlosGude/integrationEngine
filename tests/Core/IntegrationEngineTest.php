<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Exception\ActionNotFoundException;
use IntegrationEngine\Core\Exception\InvalidMapperException;
use IntegrationEngine\Core\Exception\NotMappedActionException;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Core\Response\EmptyResponse;
use IntegrationEngine\Tests\Support\FakeCache;
use IntegrationEngine\Tests\Support\FakeClient;
use IntegrationEngine\Tests\Support\FakeConfigPort;
use IntegrationEngine\Tests\Support\Fixtures\Action\ActionFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
final class IntegrationEngineTest extends TestCase
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

    public function testItExecutesActionAndReturnsMappedResponse(): void
    {
        $action = ActionFactory::getHelloWorld();

        $this->config->setAction(
            name: $action::getName(),
            action: $action,
        );

        $expectedResponse = ['hello world'];

        $this->client->setResponse(
            actionName: $action::getName(),
            response: $expectedResponse
        );

        $response = $this->engine->send(actionName: $action::getName());

        self::assertSame(
            $expectedResponse,
            $response->toArray(),
        );
    }

    public function testActionWithoutResponse(): void
    {
        $action = ActionFactory::deleteFixtureAction();

        $this->config->setAction(
            name: $action::getName(),
            action: $action,
        );

        $response = $this->engine->send(actionName: $action::getName());

        self::assertInstanceOf(EmptyResponse::class, $response);
        self::assertSame([], $response->toArray());
    }

    public function testNotMappedActionException(): void
    {
        $action = ActionFactory::getNoMappedAction();

        $this->config->setAction(
            name: $action::getName(),
            action: $action,
        );

        $this->expectException(NotMappedActionException::class);
        $this->engine->send(actionName: $action::getName());
    }

    public function testActionWithNotValidMapper(): void
    {
        $action = ActionFactory::getNotValidMappedAction();

        $this->config->setAction(
            name: $action::getName(),
            action: $action,
        );

        $this->expectException(InvalidMapperException::class);
        $this->engine->send(actionName: $action::getName());
    }

    public function testItHandlesActionNotFound(): void
    {
        $actionName = 'nonexistent_action';

        $this->expectException(ActionNotFoundException::class);
        $this->engine->send(actionName: $actionName);
    }
}
