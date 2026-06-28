<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Bundle\DependencyInjection;

use IntegrationEngine\Bundle\DependencyInjection\Compiler\IntegrationCompilerPass;
use IntegrationEngine\Bundle\Exception\IntegrationConfigurationException;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Core\Registry\IntegrationRegistry;
use IntegrationEngine\Infrastructure\Adapter\YamlConfigAdapter;
use IntegrationEngine\Infrastructure\Cache\CachingMiddleware;
use IntegrationEngine\Infrastructure\Client\MiddlewareClient;
use IntegrationEngine\Infrastructure\Http\ClientAdapterResolver;
use IntegrationEngine\Infrastructure\Http\GraphQLClientAdapter;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use IntegrationEngine\Tests\Fake\FakeMiddleware;
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

        $httpClientDef = $container->getDefinition('integration_engine.http_client.my_api');
        self::assertSame(SymfonyHttpClientAdapter::class, $httpClientDef->getClass());
        self::assertSame('https://api.example.com', $httpClientDef->getArgument(1));
        self::assertSame(['X-Tenant' => 'acme'], $httpClientDef->getArgument(2));

        $clientDef = $container->getDefinition('integration_engine.client.my_api');
        self::assertSame(MiddlewareClient::class, $clientDef->getClass());
        self::assertSame('integration_engine.http_client.my_api', $this->referencedServiceId($clientDef->getArgument(0)));

        $engineDef = $container->getDefinition('integration_engine.integration.my_api');
        self::assertSame(IntegrationEngine::class, $engineDef->getClass());
        self::assertSame('integration_engine.config.my_api', $this->referencedServiceId($engineDef->getArgument(0)));
        self::assertSame('integration_engine.client.my_api', $this->referencedServiceId($engineDef->getArgument(1)));
        self::assertSame('integration_engine.cache.default', $this->referencedServiceId($engineDef->getArgument(2)));
        self::assertSame('my_api', $engineDef->getArgument(3));

        /** @var list<array{string, array<int, mixed>}> $registryCalls */
        $registryCalls = $container->getDefinition(IntegrationRegistry::class)->getMethodCalls();
        self::assertCount(1, $registryCalls);
        self::assertSame('register', $registryCalls[0][0]);
        self::assertSame('my_api', $registryCalls[0][1][0]);
        self::assertSame('integration_engine.integration.my_api', $this->referencedServiceId($registryCalls[0][1][1]));
    }

    #[Test]
    public function wiresIntegrationWithGraphqlAdapter(): void
    {
        $container = $this->containerWithCoreServices([
            'my_api' => $this->integrationConfig(['client' => 'graphql']),
        ]);

        (new IntegrationCompilerPass())->process($container);

        $httpClientDef = $container->getDefinition('integration_engine.http_client.my_api');
        self::assertSame(GraphQLClientAdapter::class, $httpClientDef->getClass());
    }

    #[Test]
    public function alwaysUsesMiddlewareClientRegardlessOfBatchCapability(): void
    {
        foreach (['rest', 'graphql'] as $clientType) {
            $container = $this->containerWithCoreServices([
                'my_api' => $this->integrationConfig(['client' => $clientType]),
            ]);

            (new IntegrationCompilerPass())->process($container);

            $clientDef = $container->getDefinition('integration_engine.client.my_api');
            self::assertSame(MiddlewareClient::class, $clientDef->getClass(), "Expected MiddlewareClient for {$clientType}");
        }
    }

    #[Test]
    public function wiresCachingMiddlewareAlways(): void
    {
        $container = $this->containerWithCoreServices([
            'my_api' => $this->integrationConfig(),
        ]);

        (new IntegrationCompilerPass())->process($container);

        $cachingDef = $container->getDefinition('integration_engine.middleware.caching.my_api');
        self::assertSame(CachingMiddleware::class, $cachingDef->getClass());
        self::assertSame('integration_engine.cache.default', $this->referencedServiceId($cachingDef->getArgument(0)));
        self::assertSame('my_api', $cachingDef->getArgument(1));
    }

    #[Test]
    public function declaredMiddlewaresAreInjectedBetweenCachingAndAdapter(): void
    {
        $container = $this->containerWithCoreServices(['my_api' => $this->integrationConfig([
            'middlewares' => ['app.rate_limit', 'app.retry'],
        ])]);
        $this->tagMiddleware($container, 'app.rate_limit', FakeMiddleware::class);
        $this->tagMiddleware($container, 'app.retry', FakeMiddleware::class);

        (new IntegrationCompilerPass())->process($container);

        $middlewares = $container->getDefinition('integration_engine.client.my_api')->getArgument(1);
        self::assertCount(3, $middlewares);

        $cachingDef = $container->getDefinition($this->referencedServiceId($middlewares[0]));
        self::assertSame(CachingMiddleware::class, $cachingDef->getClass());

        self::assertSame('app.rate_limit', $this->referencedServiceId($middlewares[1]));
        self::assertSame('app.retry', $this->referencedServiceId($middlewares[2]));
    }

    #[Test]
    public function middlewareOrderFollowsDeclarationOrder(): void
    {
        $container = $this->containerWithCoreServices(['my_api' => $this->integrationConfig([
            'middlewares' => ['app.outer', 'app.middle', 'app.inner'],
        ])]);
        $this->tagMiddleware($container, 'app.inner', FakeMiddleware::class);
        $this->tagMiddleware($container, 'app.outer', FakeMiddleware::class);
        $this->tagMiddleware($container, 'app.middle', FakeMiddleware::class);

        (new IntegrationCompilerPass())->process($container);

        $middlewares = $container->getDefinition('integration_engine.client.my_api')->getArgument(1);
        self::assertSame('app.outer', $this->referencedServiceId($middlewares[1]));
        self::assertSame('app.middle', $this->referencedServiceId($middlewares[2]));
        self::assertSame('app.inner', $this->referencedServiceId($middlewares[3]));
    }

    #[Test]
    public function taggedMiddlewareNotDeclaredInIntegrationIsNotInjected(): void
    {
        $container = $this->containerWithCoreServices(['my_api' => $this->integrationConfig([
            'middlewares' => ['app.rate_limit'],
        ])]);
        $this->tagMiddleware($container, 'app.rate_limit', FakeMiddleware::class);
        $this->tagMiddleware($container, 'app.retry', FakeMiddleware::class);

        (new IntegrationCompilerPass())->process($container);

        $middlewares = $container->getDefinition('integration_engine.client.my_api')->getArgument(1);
        self::assertCount(2, $middlewares);
        self::assertSame('app.rate_limit', $this->referencedServiceId($middlewares[1]));
    }

    #[Test]
    public function throwsWhenDeclaredMiddlewareIsNotTagged(): void
    {
        $container = $this->containerWithCoreServices(['my_api' => $this->integrationConfig([
            'middlewares' => ['app.not_registered'],
        ])]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/my_api.*app\.not_registered/');

        (new IntegrationCompilerPass())->process($container);
    }

    #[Test]
    public function withNoTaggedMiddlewaresOnlyCachingIsInTheChain(): void
    {
        $container = $this->containerWithCoreServices(['my_api' => $this->integrationConfig()]);

        (new IntegrationCompilerPass())->process($container);

        $middlewares = $container->getDefinition('integration_engine.client.my_api')->getArgument(1);
        self::assertCount(1, $middlewares);
    }

    #[Test]
    public function throwsWhenTaggedMiddlewareClassDoesNotExist(): void
    {
        $container = $this->containerWithCoreServices(['my_api' => $this->integrationConfig()]);
        $this->tagMiddleware($container, 'app.bogus', 'App\Does\Not\Exist');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/app\.bogus.*App\\\Does\\\Not\\\Exist/');

        (new IntegrationCompilerPass())->process($container);
    }

    #[Test]
    public function throwsWhenTaggedMiddlewareDoesNotImplementInterface(): void
    {
        $container = $this->containerWithCoreServices(['my_api' => $this->integrationConfig()]);
        $this->tagMiddleware($container, 'app.not_a_mw', \stdClass::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/app\.not_a_mw.*stdClass/');

        (new IntegrationCompilerPass())->process($container);
    }

    #[Test]
    public function usesCustomClientServiceInsteadOfBuildingAnAdapter(): void
    {
        $container = $this->containerWithCoreServices([
            'my_api' => $this->integrationConfig(['client_service' => 'app.custom_client', 'base_url' => null]),
        ]);

        (new IntegrationCompilerPass())->process($container);

        self::assertFalse($container->hasDefinition('integration_engine.http_client.my_api'));

        $clientDef = $container->getDefinition('integration_engine.client.my_api');
        self::assertSame('app.custom_client', $this->referencedServiceId($clientDef->getArgument(0)));
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

    private function referencedServiceId(mixed $argument): string
    {
        self::assertInstanceOf(Reference::class, $argument);

        return (string) $argument;
    }

    private function tagMiddleware(ContainerBuilder $container, string $serviceId, string $class, int $priority = 0): void
    {
        $def = new Definition($class);
        $def->addTag('integration_engine.middleware', ['priority' => $priority]);
        $container->setDefinition($serviceId, $def);
    }

    /** @param array<string, array<string, mixed>> $integrations */
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

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function integrationConfig(array $overrides = []): array
    {
        return array_merge([
            'config_path' => '/tmp/MyApi.yaml',
            'base_url' => 'https://api.example.com',
            'client' => 'rest',
            'client_service' => null,
            'cache_service' => null,
            'headers' => [],
            'middlewares' => [],
        ], $overrides);
    }
}
