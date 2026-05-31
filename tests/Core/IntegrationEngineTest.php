<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\IntegrationEngine;
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
            $action::getName(),
            $action,
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
}
