<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Bundle\DependencyInjection;

use IntegrationEngine\Bundle\DependencyInjection\Compiler\IntegrationCompilerPass;
use IntegrationEngine\Bundle\Exception\IntegrationConfigurationException;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Core\Registry\IntegrationRegistry;
use IntegrationEngine\Infrastructure\Adapter\YamlConfigAdapter;
use IntegrationEngine\Infrastructure\Http\ClientAdapterResolver;
use IntegrationEngine\Infrastructure\Http\GraphQLClientAdapter;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class IntegrationCompilerPassTest extends TestCase
{
    #[Test]
    public function doesNothingWhenIntegrationsParameterIsMissing(): void
    {
        // No parameter, no core service definitions: the pass must return
        // before calling findDefinition() — otherwise it would throw.
        $container = new ContainerBuilder();

        (new IntegrationCompilerPass())->process($container);

        self::assertFalse($container->hasDefinition(ClientAdapterResolver::class));
    }

    #[Test]
    public function registersTaggedAdaptersInResolver(): void
    {
        $container = $this->containerWithCoreServices([]);

        (new IntegrationCompilerPass())->process($container);

        $calls = $container->getDefinition(ClientAdapterResolver::class)->getMethodCalls();

        self::assertContains(['register', ['rest', SymfonyHttpClientAdapter::class]], $calls);
        self::assertContains(['register', ['graphql', GraphQLClientAdapter::class]], $calls);
    }

    #[Test]
    public function throwsWhenTaggedAdapterClassDoesNotExist(): void
    {
        $container = $this->containerWithCoreServices([]);

        $bogus = new Definition('App\Does\Not\Exist');
        $bogus->addTag('integration_engine.client_adapter');
        $container->setDefinition('bogus_adapter', $bogus);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/bogus_adapter.*App\\\Does\\\Not\\\Exist/');

        (new IntegrationCompilerPass())->process($container);
    }

    #[Test]
    public function throwsWhenTaggedServiceDoesNotImplementClientAdapterInterface(): void
    {
        $container = $this->containerWithCoreServices([]);

        $notAnAdapter = new Definition(\stdClass::class);
        $notAnAdapter->addTag('integration_engine.client_adapter');
        $container->setDefinition('not_an_adapter', $notAnAdapter);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not_an_adapter.*stdClass/');

        (new IntegrationCompilerPass())->process($container);
    }

    #[Test]
    public function wiresIntegrationWithBuiltInRestAdapter(): void
    {
        $container = $this->containerWithCoreServices([
            'my_api' => $this->integrationConfig(['headers' => ['X-Tenant' => 'acme']]),
        ]);

        (new IntegrationCompilerPass())->process($container);

        $configDef = $container->getDefinition('integration_engine.config.my_api');
        self::assertSame(YamlConfigAdapter::class, $configDef->getClass());
        self::assertSame('/tmp/MyApi.yaml', $configDef->getArgument(0));

        $clientDef = $container->getDefinition('integration_engine.http_client.my_api');
        self::assertSame(SymfonyHttpClientAdapter::class, $clientDef->getClass());
        self::assertSame('http_client', $this->referencedServiceId($clientDef->getArgument(0)));
        self::assertSame('https://api.example.com', $clientDef->getArgument(1));
        self::assertSame(['X-Tenant' => 'acme'], $clientDef->getArgument(2));

        $engineDef = $container->getDefinition('integration_engine.integration.my_api');
        self::assertSame(IntegrationEngine::class, $engineDef->getClass());
        self::assertSame('integration_engine.config.my_api', $this->referencedServiceId($engineDef->getArgument(0)));
        self::assertSame('integration_engine.http_client.my_api', $this->referencedServiceId($engineDef->getArgument(1)));
        self::assertSame('integration_engine.cache.default', $this->referencedServiceId($engineDef->getArgument(2)));
        self::assertSame('my_api', $engineDef->getArgument(3));

        /** @var list<array{string, array<int, mixed>}> $registryCalls */
        $registryCalls = $container->getDefinition(IntegrationRegistry::class)->getMethodCalls();
        self::assertCount(1, $registryCalls);
        self::assertSame('register', $registryCalls[0][0]);
        self::assertSame('my_api', $registryCalls[0][1][0]);
        self::assertSame(
            'integration_engine.integration.my_api',
            $this->referencedServiceId($registryCalls[0][1][1])
        );
    }

    #[Test]
    public function wiresIntegrationWithGraphqlAdapter(): void
    {
        $container = $this->containerWithCoreServices([
            'my_api' => $this->integrationConfig(['client' => 'graphql']),
        ]);

        (new IntegrationCompilerPass())->process($container);

        $clientDef = $container->getDefinition('integration_engine.http_client.my_api');
        self::assertSame(GraphQLClientAdapter::class, $clientDef->getClass());
    }

    #[Test]
    public function usesCustomClientServiceInsteadOfBuildingAnAdapter(): void
    {
        $container = $this->containerWithCoreServices([
            'my_api' => $this->integrationConfig(['client_service' => 'app.custom_client', 'base_url' => null]),
        ]);

        (new IntegrationCompilerPass())->process($container);

        self::assertFalse($container->hasDefinition('integration_engine.http_client.my_api'));

        $engineDef = $container->getDefinition('integration_engine.integration.my_api');
        self::assertSame('app.custom_client', $this->referencedServiceId($engineDef->getArgument(1)));
    }

    #[Test]
    public function usesCustomCacheServiceWhenConfigured(): void
    {
        $container = $this->containerWithCoreServices([
            'my_api' => $this->integrationConfig(['cache_service' => 'app.redis_cache']),
        ]);

        (new IntegrationCompilerPass())->process($container);

        $engineDef = $container->getDefinition('integration_engine.integration.my_api');
        self::assertSame('app.redis_cache', $this->referencedServiceId($engineDef->getArgument(2)));
    }

    #[Test]
    public function throwsWhenConfigPathIsMissing(): void
    {
        $container = $this->containerWithCoreServices([
            'my_api' => $this->integrationConfig(['config_path' => null]),
        ]);

        $this->expectException(IntegrationConfigurationException::class);
        $this->expectExceptionMessageMatches('/must define "config_path"/');

        (new IntegrationCompilerPass())->process($container);
    }

    #[Test]
    public function throwsWhenClientTypeIsUnknown(): void
    {
        $container = $this->containerWithCoreServices([
            'my_api' => $this->integrationConfig(['client' => 'soap']),
        ]);

        $this->expectException(IntegrationConfigurationException::class);
        $this->expectExceptionMessageMatches('/Unknown client type "soap".*Registered types: rest, graphql/');

        (new IntegrationCompilerPass())->process($container);
    }

    /**
     * References are value objects rebuilt by the pass — identity can never
     * hold, so we compare the referenced service id instead.
     */
    private function referencedServiceId(mixed $argument): string
    {
        self::assertInstanceOf(Reference::class, $argument);

        return (string) $argument;
    }

    /**
     * Builds a container with the definitions the pass expects to find:
     * resolver, registry and the two built-in adapters tagged.
     *
     * @param array<string, array<string, mixed>> $integrations
     */
    private function containerWithCoreServices(array $integrations): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('integration_engine.integrations', $integrations);

        $container->setDefinition(ClientAdapterResolver::class, new Definition(ClientAdapterResolver::class));
        $container->setDefinition(IntegrationRegistry::class, new Definition(IntegrationRegistry::class));

        foreach ([SymfonyHttpClientAdapter::class, GraphQLClientAdapter::class] as $adapterClass) {
            $definition = new Definition($adapterClass);
            $definition->addTag('integration_engine.client_adapter');
            $container->setDefinition($adapterClass, $definition);
        }

        return $container;
    }

    /**
     * A fully-resolved integration config as produced by Configuration.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function integrationConfig(array $overrides = []): array
    {
        return array_merge([
            'config_path' => '/tmp/MyApi.yaml',
            'base_url' => 'https://api.example.com',
            'client' => 'rest',
            'client_service' => null,
            'cache_service' => null,
            'headers' => [],
        ], $overrides);
    }
}
