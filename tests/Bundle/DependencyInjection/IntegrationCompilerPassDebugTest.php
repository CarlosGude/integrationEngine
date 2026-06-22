<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Bundle\DependencyInjection;

use IntegrationEngine\Bundle\DependencyInjection\Compiler\IntegrationCompilerPass;
use IntegrationEngine\Core\Registry\IntegrationRegistry;
use IntegrationEngine\Infrastructure\Debug\IntegrationEngineDataCollector;
use IntegrationEngine\Infrastructure\Debug\TraceableBatchClient;
use IntegrationEngine\Infrastructure\Debug\TraceableClient;
use IntegrationEngine\Infrastructure\Http\ClientAdapterResolver;
use IntegrationEngine\Infrastructure\Http\GraphQLClientAdapter;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class IntegrationCompilerPassDebugTest extends TestCase
{
    #[Test]
    public function withKernelDebugTrueTheRestClientIsWrappedInTraceableBatchClient(): void
    {
        // SymfonyHttpClientAdapter implements BatchClientInterface.
        $container = $this->containerWithCoreServices(['my_api' => $this->integrationConfig()], debug: true);

        (new IntegrationCompilerPass())->process($container);

        $engineDef = $container->getDefinition('integration_engine.integration.my_api');
        $traceableId = $this->referencedServiceId($engineDef->getArgument(1));

        self::assertSame('integration_engine.traceable_client.my_api', $traceableId);

        $traceableDef = $container->getDefinition($traceableId);
        self::assertSame(TraceableBatchClient::class, $traceableDef->getClass());
        self::assertSame(
            'integration_engine.http_client.my_api',
            $this->referencedServiceId($traceableDef->getArgument(0)),
        );
        self::assertSame('my_api', $traceableDef->getArgument(1));
        self::assertTrue($container->hasDefinition(IntegrationEngineDataCollector::class));
    }

    #[Test]
    public function withKernelDebugTrueTheGraphqlClientIsWrappedInTheNonBatchTraceableClient(): void
    {
        // GraphQLClientAdapter does not implement BatchClientInterface.
        $container = $this->containerWithCoreServices(
            ['my_api' => $this->integrationConfig(['client' => 'graphql'])],
            debug: true,
        );

        (new IntegrationCompilerPass())->process($container);

        $engineDef = $container->getDefinition('integration_engine.integration.my_api');
        $traceableId = $this->referencedServiceId($engineDef->getArgument(1));
        $traceableDef = $container->getDefinition($traceableId);

        self::assertSame(TraceableClient::class, $traceableDef->getClass());
    }

    #[Test]
    public function withKernelDebugTrueTheSameDecoratedClientIsUsedByTheAuthHandler(): void
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

    /**
     * The most important no-break guarantee: without kernel.debug (or with
     * it false), the wired client is exactly the original definition —
     * unchanged from before this feature existed.
     */
    #[Test]
    public function withoutKernelDebugTheClientIsNotWrapped(): void
    {
        $container = $this->containerWithCoreServices(['my_api' => $this->integrationConfig()], debug: false);

        (new IntegrationCompilerPass())->process($container);

        $engineDef = $container->getDefinition('integration_engine.integration.my_api');

        self::assertSame(
            'integration_engine.http_client.my_api',
            $this->referencedServiceId($engineDef->getArgument(1)),
        );
        self::assertFalse($container->hasDefinition(IntegrationEngineDataCollector::class));
    }

    /**
     * Regression: Symfony's resource-based autodiscovery (services.yaml)
     * registers every *excluded* class as an abstract placeholder definition
     * tagged "container.excluded" — it does not simply skip it. Since
     * IntegrationEngineDataCollector is excluded for projects without
     * symfony/http-kernel, hasDefinition() alone returns true for that
     * placeholder; the pass must detect isAbstract() and replace it with a
     * real, usable definition rather than reusing the placeholder as-is.
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
     * The other half of the no-overhead guarantee: kernel.debug alone isn't
     * enough. Without a "profiler" service registered — the signal that
     * symfony/web-profiler-bundle is actually installed — nothing would ever
     * read the collected data, so the pass must not pay for the decoration.
     */
    #[Test]
    public function withKernelDebugTrueButNoProfilerServiceTheClientIsNotWrapped(): void
    {
        $container = $this->containerWithCoreServices(
            ['my_api' => $this->integrationConfig()],
            debug: true,
            withProfiler: false,
        );

        (new IntegrationCompilerPass())->process($container);

        $engineDef = $container->getDefinition('integration_engine.integration.my_api');

        self::assertSame(
            'integration_engine.http_client.my_api',
            $this->referencedServiceId($engineDef->getArgument(1)),
        );
        self::assertFalse($container->hasDefinition(IntegrationEngineDataCollector::class));
    }

    #[Test]
    public function withNoKernelDebugParameterAtAllTheClientIsNotWrapped(): void
    {
        // kernel.debug is set by the real Symfony kernel — a bare
        // ContainerBuilder (as in most of this pass's other tests) never
        // defines it. The pass must not throw or assume it's true.
        $container = $this->containerWithCoreServices(['my_api' => $this->integrationConfig()]);

        (new IntegrationCompilerPass())->process($container);

        $engineDef = $container->getDefinition('integration_engine.integration.my_api');

        self::assertSame(
            'integration_engine.http_client.my_api',
            $this->referencedServiceId($engineDef->getArgument(1)),
        );
    }

    private function referencedServiceId(mixed $argument): string
    {
        self::assertInstanceOf(Reference::class, $argument);

        return (string) $argument;
    }

    /**
     * @param array<string, array<string, mixed>> $integrations
     */
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
            // Stands in for web-profiler-bundle's real "profiler" service —
            // the pass only checks has(), never the concrete class.
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

    /**
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
