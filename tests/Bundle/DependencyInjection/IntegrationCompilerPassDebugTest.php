<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Bundle\DependencyInjection;

use IntegrationEngine\Bundle\DependencyInjection\Compiler\IntegrationCompilerPass;
use IntegrationEngine\Core\Registry\IntegrationRegistry;
use IntegrationEngine\Infrastructure\Cache\CachingMiddleware;
use IntegrationEngine\Infrastructure\Client\MiddlewareClient;
use IntegrationEngine\Infrastructure\Debug\IntegrationEngineDataCollector;
use IntegrationEngine\Infrastructure\Debug\TracingMiddleware;
use IntegrationEngine\Infrastructure\Http\ClientAdapterResolver;
use IntegrationEngine\Infrastructure\Http\GraphQLClientAdapter;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use IntegrationEngine\Tests\Fake\FakeMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class IntegrationCompilerPassDebugTest extends TestCase
{
    #[Test]
    public function withKernelDebugTrueMiddlewareClientIncludesBothMiddlewares(): void
    {
        $container = $this->containerWithCoreServices(['my_api' => $this->integrationConfig()], debug: true);

        (new IntegrationCompilerPass())->process($container);

        // Engine receives the MiddlewareClient
        $engineDef = $container->getDefinition('integration_engine.integration.my_api');
        self::assertSame('integration_engine.client.my_api', $this->referencedServiceId($engineDef->getArgument(1)));

        $clientDef = $container->getDefinition('integration_engine.client.my_api');
        self::assertSame(MiddlewareClient::class, $clientDef->getClass());
        self::assertSame('integration_engine.http_client.my_api', $this->referencedServiceId($clientDef->getArgument(0)));

        // Middleware list: [CachingMiddleware (outermost), TracingMiddleware]
        $middlewares = $clientDef->getArgument(1);
        self::assertIsArray($middlewares);
        self::assertCount(2, $middlewares);

        $cachingDef = $container->getDefinition($this->referencedServiceId($middlewares[0]));
        self::assertSame(CachingMiddleware::class, $cachingDef->getClass());

        $tracingDef = $container->getDefinition($this->referencedServiceId($middlewares[1]));
        self::assertSame(TracingMiddleware::class, $tracingDef->getClass());
        self::assertSame('my_api', $tracingDef->getArgument(0));

        self::assertTrue($container->hasDefinition(IntegrationEngineDataCollector::class));
    }

    #[Test]
    public function declaredMiddlewaresArePositionedBetweenCachingAndTracingInDebugMode(): void
    {
        $container = $this->containerWithCoreServices(['my_api' => $this->integrationConfig([
            'middlewares' => ['app.rate_limit', 'app.retry'],
        ])], debug: true);
        $this->tagMiddleware($container, 'app.rate_limit', FakeMiddleware::class);
        $this->tagMiddleware($container, 'app.retry', FakeMiddleware::class);

        (new IntegrationCompilerPass())->process($container);

        $middlewares = $container->getDefinition('integration_engine.client.my_api')->getArgument(1);
        // [CachingMiddleware, app.rate_limit, app.retry, TracingMiddleware]
        self::assertCount(4, $middlewares);

        $cachingDef = $container->getDefinition($this->referencedServiceId($middlewares[0]));
        self::assertSame(CachingMiddleware::class, $cachingDef->getClass());

        self::assertSame('app.rate_limit', $this->referencedServiceId($middlewares[1]));
        self::assertSame('app.retry', $this->referencedServiceId($middlewares[2]));

        $tracingDef = $container->getDefinition($this->referencedServiceId($middlewares[3]));
        self::assertSame(TracingMiddleware::class, $tracingDef->getClass());
    }

    #[Test]
    public function withKernelDebugTrueTracingMiddlewareIsAddedForBothRestAndGraphql(): void
    {
        foreach (['rest', 'graphql'] as $clientType) {
            $container = $this->containerWithCoreServices(
                ['my_api' => $this->integrationConfig(['client' => $clientType])],
                debug: true,
            );

            (new IntegrationCompilerPass())->process($container);

            $clientDef = $container->getDefinition('integration_engine.client.my_api');
            $middlewares = $clientDef->getArgument(1);
            self::assertCount(2, $middlewares, "Expected 2 middlewares for {$clientType}");

            $tracingDef = $container->getDefinition($this->referencedServiceId($middlewares[1]));
            self::assertSame(TracingMiddleware::class, $tracingDef->getClass());
        }
    }

    #[Test]
    public function withKernelDebugTrueTheSameClientIsUsedByBothEngineAndAuthHandler(): void
    {
        $container = $this->containerWithCoreServices(['my_api' => $this->integrationConfig()], debug: true);

        (new IntegrationCompilerPass())->process($container);

        $engineDef = $container->getDefinition('integration_engine.integration.my_api');
        $authHandlerDef = $container->getDefinition('integration_engine.auth_handler.my_api');

        self::assertSame(
            $this->referencedServiceId($engineDef->getArgument(1)),
            $this->referencedServiceId($authHandlerDef->getArgument(1)),
        );
    }

    #[Test]
    public function withoutKernelDebugOnlyCachingMiddlewareIsWired(): void
    {
        $container = $this->containerWithCoreServices(['my_api' => $this->integrationConfig()], debug: false);

        (new IntegrationCompilerPass())->process($container);

        $clientDef = $container->getDefinition('integration_engine.client.my_api');
        $middlewares = $clientDef->getArgument(1);
        self::assertCount(1, $middlewares);

        $cachingDef = $container->getDefinition($this->referencedServiceId($middlewares[0]));
        self::assertSame(CachingMiddleware::class, $cachingDef->getClass());

        self::assertFalse($container->hasDefinition(IntegrationEngineDataCollector::class));
        self::assertFalse($container->hasDefinition('integration_engine.middleware.tracing.my_api'));
    }

    /**
     * Regression: Symfony registers excluded classes as abstract placeholder
     * definitions. The pass must detect isAbstract() and replace the placeholder.
     */
    #[Test]
    public function replacesAnExcludedAbstractPlaceholderWithARealDataCollectorDefinition(): void
    {
        $container = $this->containerWithCoreServices(['my_api' => $this->integrationConfig()], debug: true);
        $container->setDefinition(IntegrationEngineDataCollector::class, (new Definition(
            IntegrationEngineDataCollector::class,
        ))->setAbstract(true)->addTag('container.excluded'));

        (new IntegrationCompilerPass())->process($container);

        $collectorDef = $container->getDefinition(IntegrationEngineDataCollector::class);
        self::assertFalse($collectorDef->isAbstract());
        self::assertNotEmpty($collectorDef->getTag('data_collector'));
    }

    /**
     * kernel.debug alone is not enough — without the "profiler" service the
     * TracingMiddleware must not be wired (nothing would read the data).
     */
    #[Test]
    public function withKernelDebugButNoProfilerServiceTracingMiddlewareIsNotWired(): void
    {
        $container = $this->containerWithCoreServices(
            ['my_api' => $this->integrationConfig()],
            debug: true,
            withProfiler: false,
        );

        (new IntegrationCompilerPass())->process($container);

        $clientDef = $container->getDefinition('integration_engine.client.my_api');
        self::assertCount(1, $clientDef->getArgument(1));
        self::assertFalse($container->hasDefinition(IntegrationEngineDataCollector::class));
    }

    #[Test]
    public function withNoKernelDebugParameterAtAllTracingMiddlewareIsNotWired(): void
    {
        $container = $this->containerWithCoreServices(['my_api' => $this->integrationConfig()]);

        (new IntegrationCompilerPass())->process($container);

        $clientDef = $container->getDefinition('integration_engine.client.my_api');
        self::assertCount(1, $clientDef->getArgument(1));
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
    private function containerWithCoreServices(
        array $integrations,
        ?bool $debug = null,
        bool $withProfiler = true,
    ): ContainerBuilder {
        $container = new ContainerBuilder();
        $container->setParameter('integration_engine.integrations', $integrations);

        if (null !== $debug) {
            $container->setParameter('kernel.debug', $debug);
        }

        if ($withProfiler) {
            $container->setDefinition('profiler', new Definition(\stdClass::class));
        }

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
