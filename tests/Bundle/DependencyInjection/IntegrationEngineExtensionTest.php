<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Bundle\DependencyInjection;

use IntegrationEngine\Bundle\Command\DebugIntegrationCommand;
use IntegrationEngine\Bundle\DependencyInjection\Compiler\IntegrationCompilerPass;
use IntegrationEngine\Bundle\DependencyInjection\IntegrationEngineExtension;
use IntegrationEngine\Bundle\IntegrationEngineBundle;
use IntegrationEngine\Core\Resilience\ErrorClassifier;
use IntegrationEngine\Core\Resilience\ExponentialBackoffPolicy;
use IntegrationEngine\Infrastructure\Adapter\FormEncodedClientAdapter;
use IntegrationEngine\Infrastructure\Http\ClientAdapterResolver;
use IntegrationEngine\Infrastructure\Http\GraphQLClientAdapter;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\Exception\TransportException;

final class IntegrationEngineExtensionTest extends TestCase
{
    #[Test]
    public function legacyResilienceServiceIdsStillResolveAfterClassmapMigration(): void
    {
        $container = $this->load(['integrations' => []]);
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->getDefinition(ErrorClassifier::class)->setPublic(true);
        $container->getDefinition(ExponentialBackoffPolicy::class)->setPublic(true);
        $container->compile();

        self::assertInstanceOf(ErrorClassifier::class, $container->get(ErrorClassifier::class));
        $policy = $container->get(ExponentialBackoffPolicy::class);
        self::assertInstanceOf(ExponentialBackoffPolicy::class, $policy);
        self::assertTrue($policy->shouldRetry(new TransportException('network'), 1));
    }

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

        foreach ([SymfonyHttpClientAdapter::class, GraphQLClientAdapter::class, FormEncodedClientAdapter::class] as $adapterClass) {
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
    public function inspectionCommandReceivesTheProcessedConfiguration(): void
    {
        $container = $this->load(['integrations' => [
            'orders' => ['base_url' => 'https://example.com', 'config_path' => '/tmp/orders.yaml'],
        ]]);
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $definition = $container->getDefinition(DebugIntegrationCommand::class);
        $definition->setPublic(true);
        $container->compile();

        $command = $container->get(DebugIntegrationCommand::class);
        self::assertInstanceOf(DebugIntegrationCommand::class, $command);
        $tester = new CommandTester($command);
        self::assertSame(0, $tester->execute(['--format' => 'json']));
        self::assertSame(['integrations' => [
            ['name' => 'orders', 'client' => 'rest', 'config_path' => '/tmp/orders.yaml'],
        ]], json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function formEncodedClientIsWiredFromThePublicConfiguration(): void
    {
        $container = $this->load(['integrations' => [
            'forms' => [
                'client' => 'form_encoded',
                'base_url' => 'https://example.com',
                'config_path' => '/tmp/forms.yaml',
                'headers' => ['X-Tenant' => 'acme'],
            ],
        ]]);

        (new IntegrationCompilerPass())->process($container);

        $client = $container->getDefinition('integration_engine.http_client.forms');
        self::assertSame(FormEncodedClientAdapter::class, $client->getClass());
        self::assertSame('https://example.com', $client->getArgument(1));
        self::assertSame(['X-Tenant' => 'acme'], $client->getArgument(2));
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
