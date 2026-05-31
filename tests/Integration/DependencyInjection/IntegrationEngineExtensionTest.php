<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Integration\DependencyInjection;

use IntegrationEngine\Bundle\DependencyInjection\IntegrationEngineExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
final class IntegrationEngineExtensionTest extends TestCase
{
    public function testItLoadsIntegrationsConfiguration(): void
    {
        $container = new ContainerBuilder();

        $extension = new IntegrationEngineExtension();

        $extension->load([
            [
                'integrations' => [
                    'stripe' => [
                        'config_path' => '/tmp/stripe.yaml',
                        'base_url' => 'https://api.stripe.com',
                        'client_service' => null,
                        'cache_service' => null,
                    ],
                ],
            ],
        ], $container);

        // 🔥 Solo validamos que la config se ha registrado como parámetro
        $this->assertTrue(
            $container->hasParameter('integration_engine.integrations')
        );

        $this->assertSame(
            [
                'stripe' => [
                    'config_path' => '/tmp/stripe.yaml',
                    'base_url' => 'https://api.stripe.com',
                    'client_service' => null,
                    'cache_service' => null,
                ],
            ],
            $container->getParameter('integration_engine.integrations')
        );
    }
}
