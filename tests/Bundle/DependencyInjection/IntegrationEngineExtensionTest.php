<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Bundle\DependencyInjection;

use IntegrationEngine\Bundle\DependencyInjection\Compiler\IntegrationCompilerPass;
use IntegrationEngine\Bundle\DependencyInjection\IntegrationEngineExtension;
use IntegrationEngine\Bundle\IntegrationEngineBundle;
use IntegrationEngine\Infrastructure\Http\ClientAdapterResolver;
use IntegrationEngine\Infrastructure\Http\GraphQLClientAdapter;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class IntegrationEngineExtensionTest extends TestCase
{
    #[Test]
    public function loadExposesProcessedIntegrationsAsParameter(): void
    {
        $container = $this->load([
            'integrations' => [
                'my_api' => [
                    'base_url' => 'https://api.example.com',
                    'config_path' => '/tmp/MyApi.yaml',
                ],
            ],
        ]);

        /** @var array<string, array<string, mixed>> $integrations */
        $integrations = $container->getParameter('integration_engine.integrations');

        self::assertArrayHasKey('my_api', $integrations);
        self::assertSame('https://api.example.com', $integrations['my_api']['base_url']);
        // Defaults from Configuration must already be resolved here.
        self::assertSame('rest', $integrations['my_api']['client']);
    }

    #[Test]
    public function loadRegistersBuiltInAdaptersWithClientAdapterTag(): void
    {
        $container = $this->load(['integrations' => []]);

        foreach ([SymfonyHttpClientAdapter::class, GraphQLClientAdapter::class] as $adapterClass) {
            self::assertTrue($container->hasDefinition($adapterClass));
            self::assertTrue(
                $container->getDefinition($adapterClass)->hasTag('integration_engine.client_adapter'),
                \sprintf('%s must be tagged as client adapter.', $adapterClass)
            );
        }
    }

    #[Test]
    public function loadRegistersResolverAndDefaultCache(): void
    {
        $container = $this->load(['integrations' => []]);

        self::assertTrue($container->hasDefinition(ClientAdapterResolver::class));
        self::assertTrue($container->hasDefinition('integration_engine.cache.default'));
    }

    #[Test]
    public function bundleRegistersTheCompilerPass(): void
    {
        $container = new ContainerBuilder();

        (new IntegrationEngineBundle())->build($container);

        $passes = $container->getCompilerPassConfig()->getBeforeOptimizationPasses();
        $found = array_filter($passes, static fn (object $pass): bool => $pass instanceof IntegrationCompilerPass);

        self::assertCount(1, $found);
    }

    /** @param array<string, mixed> $config */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new IntegrationEngineExtension())->load([$config], $container);

        return $container;
    }
}
