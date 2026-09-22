<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Contract\Connection\ConnectionCredentials;
use IntegrationEngine\Core\Contract\Connection\ConnectionResolverInterface;
use IntegrationEngine\Core\Dispatch\ConnectionResolver;
use IntegrationEngine\Tests\Fake\FakePathAction;
use PHPUnit\Framework\TestCase;

final class ConnectionResolverScalarTest extends TestCase
{
    public function testFractionalScalarConnectionsDoNotShareCachedCredentials(): void
    {
        $applicationResolver = new class implements ConnectionResolverInterface {
            public int $calls = 0;

            public function resolve(mixed $connection): ConnectionCredentials
            {
                if (!\is_float($connection)) {
                    throw new \InvalidArgumentException('This resolver expects a fractional scalar identifier.');
                }
                ++$this->calls;

                return new ConnectionCredentials(baseUrl: 'https://example.com/tenant/'.(string) $connection);
            }
        };
        $resolver = new ConnectionResolver('test', $applicationResolver);
        $action = FakePathAction::create('GET', '/items');
        $cache = [];

        self::assertSame('https://example.com/tenant/1.1', $resolver->resolveForDispatch($action, 1.1, null, $cache)['baseUrl']);
        self::assertSame('https://example.com/tenant/1.2', $resolver->resolveForDispatch($action, 1.2, null, $cache)['baseUrl']);
        self::assertSame('https://example.com/tenant/1.1', $resolver->resolveForDispatch($action, 1.1, null, $cache)['baseUrl']);
        self::assertSame(2, $applicationResolver->calls);
    }
}
