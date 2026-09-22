<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Batch\EngineRequest;
use IntegrationEngine\Core\Contract\Connection\ConnectionCredentials;
use IntegrationEngine\Core\Exception\DisallowedHostException;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Core\Security\HostPolicy;
use IntegrationEngine\Tests\Fake\FakeConnectionResolver;
use IntegrationEngine\Tests\Fake\FakePathAction;

final class EngineHostPolicyTest extends IntegrationEngineTestCase
{
    public function testOverrideIsRejectedBeforeAnyRequest(): void
    {
        $this->configure();

        try {
            $this->engine->send(FakePathAction::getName(), baseUrl: 'https://evil.example');
            self::fail('Expected rejected destination.');
        } catch (DisallowedHostException) {
            self::assertSame(0, $this->client->callCount(FakePathAction::getName()));
        }
    }

    public function testResolverAndBatchUseTheSamePolicy(): void
    {
        $resolver = new FakeConnectionResolver();
        $resolver->register('evil', new ConnectionCredentials(baseUrl: 'https://evil.example'));
        $this->configure($resolver);
        $results = $this->engine->sendMany([
            'bad' => new EngineRequest(FakePathAction::getName(), connection: 'evil'),
            'good' => new EngineRequest(FakePathAction::getName()),
        ]);
        self::assertSame(1, $this->client->callCount(FakePathAction::getName()));
        self::assertInstanceOf(DisallowedHostException::class, $results['bad']->error());
        self::assertTrue($results['good']->isSuccess());
    }

    private function configure(?FakeConnectionResolver $resolver = null): void
    {
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/items'));
        $this->engine = new IntegrationEngine(
            $this->config,
            $this->client,
            $this->cache,
            'test',
            connectionResolver: $resolver,
            hostPolicy: new HostPolicy(['partner.example']),
            baseUrl: 'https://partner.example',
        );
    }
}
