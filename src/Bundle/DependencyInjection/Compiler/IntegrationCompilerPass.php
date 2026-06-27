<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\DependencyInjection\Compiler;

use IntegrationEngine\Bundle\Exception\IntegrationConfigurationException;
use IntegrationEngine\Core\Auth\DynamicAuthHandler;
use IntegrationEngine\Core\Contract\Client\ClientAdapterInterface;
use IntegrationEngine\Core\Contract\Client\ClientMiddlewareInterface;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Core\Registry\IntegrationRegistry;
use IntegrationEngine\Infrastructure\Adapter\YamlConfigAdapter;
use IntegrationEngine\Infrastructure\Cache\CachingMiddleware;
use IntegrationEngine\Infrastructure\Client\MiddlewareClient;
use IntegrationEngine\Infrastructure\Debug\IntegrationEngineDataCollector;
use IntegrationEngine\Infrastructure\Debug\TracingMiddleware;
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

        $userMiddlewares = $this->resolveTaggedMiddlewares($container);

        $adapterMap = $this->buildAdapterMap($container);

        $registry = $container->findDefinition(IntegrationRegistry::class);

        foreach ($integrations as $name => $config) {
            $this->wireIntegration($container, $registry, $name, $config, $adapterMap, $userMiddlewares);
        }
    }

    // ── Tagged middleware discovery ────────────────────────────────────────────

    /**
     * Collects services tagged with "integration_engine.middleware", validates they
     * implement ClientMiddlewareInterface, and returns their IDs sorted by descending
     * priority (higher priority = outermost layer, same convention as Symfony event listeners).
     *
     * @return list<string>
     */
    private function resolveTaggedMiddlewares(ContainerBuilder $container): array
    {
        $tagged = $container->findTaggedServiceIds('integration_engine.middleware');

        if ([] === $tagged) {
            return [];
        }

        $entries = [];

        foreach ($tagged as $serviceId => $tags) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;

            if (!class_exists($class)) {
                throw new \InvalidArgumentException(\sprintf(
                    'Service "%s" is tagged as "integration_engine.middleware" but its class "%s" does not exist.',
                    $serviceId,
                    $class,
                ));
            }

            if (!is_a($class, ClientMiddlewareInterface::class, true)) {
                throw new \InvalidArgumentException(\sprintf(
                    'Service "%s" (%s) is tagged as "integration_engine.middleware" but does not implement %s.',
                    $serviceId,
                    $class,
                    ClientMiddlewareInterface::class,
                ));
            }

            $entries[] = [$serviceId, $this->tagPriority($tags)];
        }

        usort($entries, static fn (array $a, array $b): int => $b[1] <=> $a[1]);

        return array_column($entries, 0);
    }

    private function tagPriority(mixed $tags): int
    {
        if (!\is_array($tags) || !isset($tags[0]) || !\is_array($tags[0])) {
            return 0;
        }

        $raw = $tags[0]['priority'] ?? 0;

        return is_numeric($raw) ? (int) $raw : 0;
    }

    // ── Adapter discovery ──────────────────────────────────────────────────────

    /**
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
     * @param array{config_path: null|string, client_service: null|string, client: string, base_url: null|string, cache_service: null|string, headers: array<string, string>} $config
     * @param array<string, class-string<ClientAdapterInterface>>                                                                                                             $adapterMap
     * @param list<string>                                                                                                                                                    $userMiddlewares
     */
    private function wireIntegration(
        ContainerBuilder $container,
        Definition $registry,
        string $name,
        array $config,
        array $adapterMap,
        array $userMiddlewares,
    ): void {
        if (null === $config['config_path']) {
            throw IntegrationConfigurationException::missingConfigPath($name);
        }

        $configId = "integration_engine.config.{$name}";
        $container->setDefinition($configId, new Definition(
            YamlConfigAdapter::class,
            [$config['config_path']],
        ));

        $cacheRef = new Reference(
            $config['cache_service'] ?? 'integration_engine.cache.default',
        );

        $loggerRef = new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE);

        $httpClientRef = $this->resolveHttpClientRef($container, $name, $config, $adapterMap);
        $clientRef = $this->buildMiddlewareClient($container, $name, $httpClientRef, $cacheRef, $userMiddlewares);

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
     * Returns a Reference to the raw HTTP adapter for this integration.
     *
     * @param array{config_path: null|string, client_service: null|string, client: string, base_url: null|string, cache_service: null|string, headers: array<string, string>} $config
     * @param array<string, class-string<ClientAdapterInterface>>                                                                                                             $adapterMap
     */
    private function resolveHttpClientRef(
        ContainerBuilder $container,
        string $name,
        array $config,
        array $adapterMap,
    ): Reference {
        if ($config['client_service']) {
            return new Reference($config['client_service']);
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

        return new Reference($httpClientId);
    }

    // ── Middleware client ──────────────────────────────────────────────────────

    /**
     * Wraps the raw HTTP adapter in a MiddlewareClient. Layer order (outermost → innermost):
     * CachingMiddleware → user middlewares → TracingMiddleware (debug only) → HTTP adapter.
     *
     * Cache hits short-circuit the entire chain. Tracing wraps only the actual HTTP call,
     * not the user-middleware overhead.
     *
     * @param list<string> $userMiddlewares service IDs of ClientMiddlewareInterface implementations
     */
    private function buildMiddlewareClient(
        ContainerBuilder $container,
        string $name,
        Reference $httpClientRef,
        Reference $cacheRef,
        array $userMiddlewares,
    ): Reference {
        $collectorRef = new Reference(IntegrationEngineDataCollector::class, ContainerInterface::NULL_ON_INVALID_REFERENCE);

        $cachingId = "integration_engine.middleware.caching.{$name}";
        $container->setDefinition($cachingId, new Definition(CachingMiddleware::class, [$cacheRef, $name, $collectorRef]));

        $middlewares = [new Reference($cachingId)];

        foreach ($userMiddlewares as $serviceId) {
            $middlewares[] = new Reference($serviceId);
        }

        if ($this->shouldTrace($container)) {
            $tracingCollectorRef = $this->registerDataCollector($container);
            $tracingId = "integration_engine.middleware.tracing.{$name}";
            $container->setDefinition($tracingId, new Definition(TracingMiddleware::class, [$name, $tracingCollectorRef]));
            $middlewares[] = new Reference($tracingId);
        }

        $clientId = "integration_engine.client.{$name}";
        $container->setDefinition($clientId, new Definition(MiddlewareClient::class, [$httpClientRef, $middlewares]));

        return new Reference($clientId);
    }

    private function shouldTrace(ContainerBuilder $container): bool
    {
        return $this->isDebugging($container)
            && interface_exists(DataCollectorInterface::class)
            && $container->has('profiler');
    }

    private function isDebugging(ContainerBuilder $container): bool
    {
        return $container->hasParameter('kernel.debug') && (bool) $container->getParameter('kernel.debug');
    }

    /**
     * Registers the (single, shared) data collector definition the first time
     * it's needed — every integration reports to the same collector per request.
     */
    private function registerDataCollector(ContainerBuilder $container): Reference
    {
        $id = IntegrationEngineDataCollector::class;

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
}
