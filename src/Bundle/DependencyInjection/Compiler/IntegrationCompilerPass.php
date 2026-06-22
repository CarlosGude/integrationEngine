<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\DependencyInjection\Compiler;

use IntegrationEngine\Bundle\Exception\IntegrationConfigurationException;
use IntegrationEngine\Core\Auth\DynamicAuthHandler;
use IntegrationEngine\Core\Contract\Client\BatchClientInterface;
use IntegrationEngine\Core\Contract\Client\ClientAdapterInterface;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Core\Registry\IntegrationRegistry;
use IntegrationEngine\Infrastructure\Adapter\YamlConfigAdapter;
use IntegrationEngine\Infrastructure\Debug\IntegrationEngineDataCollector;
use IntegrationEngine\Infrastructure\Debug\TraceableBatchClient;
use IntegrationEngine\Infrastructure\Debug\TraceableClient;
use IntegrationEngine\Infrastructure\Http\ClientAdapterResolver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

final class IntegrationCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('integration_engine.integrations')) {
            return;
        }

        /** @var array<string, array{config_path: null|string, client_service: null|string, client: string, base_url: null|string, cache_service: null|string, headers: array<string, string>}> $integrations */
        $integrations = $container->getParameter('integration_engine.integrations');

        $adapterMap = $this->buildAdapterMap($container);

        $registry = $container->findDefinition(IntegrationRegistry::class);

        foreach ($integrations as $name => $config) {
            $this->wireIntegration($container, $registry, $name, $config, $adapterMap);
        }
    }

    // ── Adapter discovery ──────────────────────────────────────────────────────

    /**
     * Scans services tagged with `integration_engine.client_adapter`, validates
     * each one, and registers them in the resolver. Throws for invalid tags so
     * misconfigurations are caught at compile time rather than at runtime.
     *
     * @return array<string, class-string<ClientAdapterInterface>>
     */
    private function buildAdapterMap(ContainerBuilder $container): array
    {
        $resolverDefinition = $container->findDefinition(ClientAdapterResolver::class);

        /** @var array<string, class-string<ClientAdapterInterface>> $adapterMap */
        $adapterMap = [];

        foreach ($container->findTaggedServiceIds('integration_engine.client_adapter') as $serviceId => $tags) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;

            if (!class_exists($class)) {
                throw new \InvalidArgumentException(\sprintf(
                    'Service "%s" is tagged as "integration_engine.client_adapter" but its class "%s" does not exist.',
                    $serviceId,
                    $class,
                ));
            }

            if (!is_a($class, ClientAdapterInterface::class, true)) {
                throw new \InvalidArgumentException(\sprintf(
                    'Service "%s" (%s) is tagged as "integration_engine.client_adapter" but does not implement %s.',
                    $serviceId,
                    $class,
                    ClientAdapterInterface::class,
                ));
            }

            $adapterMap[$class::getClientType()] = $class;
            $resolverDefinition->addMethodCall('register', [$class::getClientType(), $class]);
        }

        return $adapterMap;
    }

    // ── Integration wiring ─────────────────────────────────────────────────────

    /**
     * Wires one named integration: config adapter → HTTP client → cache →
     * auth handler → engine → registry registration.
     *
     * @param array{config_path: null|string, client_service: null|string, client: string, base_url: null|string, cache_service: null|string, headers: array<string, string>} $config
     * @param array<string, class-string<ClientAdapterInterface>>                                                                                                             $adapterMap
     */
    private function wireIntegration(
        ContainerBuilder $container,
        Definition $registry,
        string $name,
        array $config,
        array $adapterMap,
    ): void {
        if (null === $config['config_path']) {
            throw IntegrationConfigurationException::missingConfigPath($name);
        }

        $configId = "integration_engine.config.{$name}";
        $container->setDefinition($configId, new Definition(
            YamlConfigAdapter::class,
            [$config['config_path']],
        ));

        $clientRef = $this->resolveClientRef($container, $name, $config, $adapterMap);

        $cacheRef = new Reference(
            $config['cache_service'] ?? 'integration_engine.cache.default',
        );

        $loggerRef = new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE);

        $authHandlerId = "integration_engine.auth_handler.{$name}";
        $container->setDefinition($authHandlerId, new Definition(
            DynamicAuthHandler::class,
            [new Reference($configId), $clientRef, $cacheRef, $name, $loggerRef],
        ));

        $integrationId = "integration_engine.integration.{$name}";
        $container->setDefinition($integrationId, new Definition(
            IntegrationEngine::class,
            [new Reference($configId), $clientRef, $cacheRef, $name, $loggerRef, new Reference($authHandlerId)],
        ));

        $registry->addMethodCall('register', [$name, new Reference($integrationId)]);
    }

    /**
     * Returns a Reference to the HTTP client for this integration: either the
     * custom client_service declared in config, or a freshly defined adapter
     * instance built from the discovered adapter map.
     *
     * @param array{config_path: null|string, client_service: null|string, client: string, base_url: null|string, cache_service: null|string, headers: array<string, string>} $config
     * @param array<string, class-string<ClientAdapterInterface>>                                                                                                             $adapterMap
     */
    private function resolveClientRef(
        ContainerBuilder $container,
        string $name,
        array $config,
        array $adapterMap,
    ): Reference {
        if ($config['client_service']) {
            $clientRef = new Reference($config['client_service']);

            return $this->decorateForDebug($container, $name, $clientRef, $this->resolveServiceClass(
                $container,
                $config['client_service'],
            ));
        }

        if (!isset($adapterMap[$config['client']])) {
            throw IntegrationConfigurationException::unknownClientType(
                $config['client'],
                $name,
                implode(', ', array_keys($adapterMap)),
            );
        }

        $adapterClass = $adapterMap[$config['client']];
        $httpClientId = "integration_engine.http_client.{$name}";

        $container->setDefinition($httpClientId, new Definition(
            $adapterClass,
            [
                new Reference('http_client'),
                $config['base_url'],
                $config['headers'],
            ],
        ));

        return $this->decorateForDebug($container, $name, new Reference($httpClientId), $adapterClass);
    }

    // ── Profiler decoration (dev/test only) ──────────────────────────────────────

    /**
     * Wraps $clientRef in TraceableClient/TraceableBatchClient when
     * kernel.debug is true, symfony/http-kernel's DataCollectorInterface is
     * available (it is not a required dependency of this bundle), AND a
     * "profiler" service is actually registered in the container — the
     * signal that symfony/web-profiler-bundle is installed and active, i.e.
     * something will actually read the collected data. Without that last
     * check, a project with http-kernel but no web-profiler-bundle would pay
     * for the decoration with nothing to show for it. In any other case,
     * returns $clientRef unchanged — no overhead, no behaviour change.
     */
    private function decorateForDebug(
        ContainerBuilder $container,
        string $name,
        Reference $clientRef,
        ?string $clientClass,
    ): Reference {
        if (
            !$this->isDebugging($container)
            || !interface_exists(DataCollectorInterface::class)
            || !$container->has('profiler')
        ) {
            return $clientRef;
        }

        $collectorRef = $this->registerDataCollector($container);
        $isBatch = null !== $clientClass && is_a($clientClass, BatchClientInterface::class, true);
        $traceableClass = $isBatch ? TraceableBatchClient::class : TraceableClient::class;
        $traceableId = "integration_engine.traceable_client.{$name}";

        $container->setDefinition($traceableId, new Definition(
            $traceableClass,
            [$clientRef, $name, $collectorRef],
        ));

        return new Reference($traceableId);
    }

    private function isDebugging(ContainerBuilder $container): bool
    {
        return $container->hasParameter('kernel.debug') && (bool) $container->getParameter('kernel.debug');
    }

    /**
     * Registers the (single, shared) data collector definition the first
     * time it's needed, so every traced integration reports to the same
     * collector instance for the current app request.
     */
    private function registerDataCollector(ContainerBuilder $container): Reference
    {
        $id = IntegrationEngineDataCollector::class;

        // Excluding this class from the blanket resource scan (services.yaml)
        // does not make it disappear from the container — Symfony registers
        // an *abstract* placeholder definition for every excluded class, so
        // excluded-but-unrelated code can still detect it was deliberately
        // skipped. hasDefinition() alone can't distinguish that placeholder
        // from a real one, so check isAbstract() too before reusing it.
        if (!$container->hasDefinition($id) || $container->getDefinition($id)->isAbstract()) {
            $definition = new Definition($id);
            $definition->addTag('data_collector', [
                'template' => '@IntegrationEngine/Collector/integration_engine.html.twig',
                'id' => 'integration_engine',
            ]);
            $container->setDefinition($id, $definition);
        }

        return new Reference($id);
    }

    /**
     * Resolves the FQCN behind a client_service id, so the engine can decide
     * whether it needs TraceableBatchClient (BatchClientInterface) instead
     * of TraceableClient. Returns null when it cannot be determined — the
     * caller then falls back to the non-batch decorator.
     */
    private function resolveServiceClass(ContainerBuilder $container, string $serviceId): ?string
    {
        if ($container->hasDefinition($serviceId)) {
            return $container->getDefinition($serviceId)->getClass() ?? $serviceId;
        }

        return class_exists($serviceId) ? $serviceId : null;
    }
}
