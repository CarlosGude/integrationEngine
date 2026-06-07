<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Tests\Fake\FakeCache;
use IntegrationEngine\Tests\Fake\FakeClient;
use IntegrationEngine\Tests\Fake\FakeConfigPort;
use PHPUnit\Framework\TestCase;

abstract class IntegrationEngineTestCase extends TestCase
{
    protected IntegrationEngine $engine;
    protected FakeConfigPort $config;
    protected FakeClient $client;
    protected FakeCache $cache;

    protected function setUp(): void
    {
        $this->config = new FakeConfigPort();
        $this->client = new FakeClient();
        $this->cache = new FakeCache();

        $this->engine = new IntegrationEngine(
            config: $this->config,
            client: $this->client,
            cache: $this->cache,
            integrationName: 'test_integration',
        );
    }
}
