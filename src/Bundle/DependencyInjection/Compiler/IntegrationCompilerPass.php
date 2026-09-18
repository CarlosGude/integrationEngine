<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\DependencyInjection\Compiler;

use IntegrationEngine\Bundle\Exception\IntegrationConfigurationException;
use IntegrationEngine\Core\Auth\DynamicAuthHandler;
use IntegrationEngine\Core\Dispatch\AuthenticationHandler;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Core\Registry\IntegrationRegistry;
use IntegrationEngine\Infrastructure\Adapter\YamlConfigAdapter;
use IntegrationEngine\Infrastructure\Cache\CachingMiddleware;
use IntegrationEngine\Infrastructure\Client\MiddlewareClient;
use IntegrationEngine\Infrastructure\Debug\IntegrationEngineDataCollector;
use IntegrationEngine\Infrastructure\Debug\TracingMiddleware;
use IntegrationEngine\Infrastructure\Http\GraphQLClientAdapter;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
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

        $integrations = $container->getParameter('integration_engine.integrations');

        $middlewareResolver = new MiddlewareResolver();
        $registeredMiddlewares = $middlewareResolver->resolveTaggedMiddlewares($container);
        $registeredRequestMiddlewares = $middlewareResolver->resolveTaggedRequestMiddlewares($container);

        $adapterBuilder = new AdapterMapBuilder();
        $adapterMap = $adapterBuilder->buildAdapterMap($container);

        $registry = $container->findDefinition(IntegrationRegistry::class);

        foreach ($integrations as $name => $config) {
            $this->wireIntegration($container, $registry, $name, $config, $adapterMap, $registeredMiddlewares, $registeredRequestMiddlewares, $middlewareResolver);
        }
    }

    private function wireIntegration(
        ContainerBuilder $container,
        Definition $registry,
        string $name,
        array $config,
        array $adapterMap,
        array $registeredMiddlewares,
        array $registeredRequestMiddlewares,
        MiddlewareResolver $middlewareResolver,
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

        $integrationRequestMiddlewares = $middlewareResolver->resolveIntegrationRequestMiddlewares($config['request_middlewares'], $registeredRequestMiddlewares, $name);
        $httpClientRef = $this->resolveHttpClientRef($container, $name, $config, $adapterMap, $integrationRequestMiddlewares);
        $integrationMiddlewares = $middlewareResolver->resolveIntegrationMiddlewares($config['middlewares'], $registeredMiddlewares, $name);
        $clientRef = $this->buildMiddlewareClient($container, $name, $httpClientRef, $cacheRef, $integrationMiddlewares);

        $authHandlerId = "integration_engine.auth_handler.{$name}";
        $container->setDefinition($authHandlerId, new Definition(
            AuthenticationHandler::class,
            [new Reference($configId), $clientRef, $cacheRef, $name, $loggerRef],
        ));

        $connectionResolverRef = $config['connection_resolver'] ? new Reference($config['connection_resolver']) : null;

        $integrationId = "integration_engine.integration.{$name}";
        $container->setDefinition($integrationId, new Definition(
            IntegrationEngine::class,
            [new Reference($configId), $clientRef, $cacheRef, $name, $loggerRef, new Reference($authHandlerId), $connectionResolverRef],
        ));

        $registry->addMethodCall('register', [$name, new Reference($integrationId)]);
    }

    /**
     * Returns a Reference to the raw HTTP adapter for this integration.
     *
     * $requestMiddlewares is only passed through as a 4th constructor
     * argument for the two built-in adapter classes, whose shared
     * constructor shape (httpClient, baseUrl, defaultHeaders, requestMiddlewares)
     * this method already assumes. A custom adapter registered via
     * integration_engine.client_adapter with a different constructor isn't
     * touched — request_middlewares silently has no effect for it, since
     * such an adapter owns its own request construction and would need to
     * support RequestMiddlewareInterface itself.
     *
     * @param array{config_path: null|string, client_service: null|string, client: string, base_url: null|string, cache_service: null|string, headers: array<string, string>} $config
     * @param array<string, class-string<ClientAdapterInterface>>                                                                                                             $adapterMap
     * @param list<string>                                                                                                                                                    $requestMiddlewares
     */
    private function resolveHttpClientRef(
        ContainerBuilder $container,
        string $name,
        array $config,
        array $adapterMap,
        array $requestMiddlewares,
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

        $args = [
            new Reference('http_client'),
            $config['base_url'],
            $config['headers'],
        ];

        if ([] !== $requestMiddlewares && \in_array($adapterClass, [SymfonyHttpClientAdapter::class, GraphQLClientAdapter::class], true)) {
            $args[] = array_map(static fn (string $id): Reference => new Reference($id), $requestMiddlewares);
        }

        $container->setDefinition($httpClientId, new Definition($adapterClass, $args));

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
     * @param list<string> $userMiddlewares service IDs of AbstractClientMiddleware subclasses
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

        // Symfony may have registered an excluded abstract placeholder instead of a real
        // definition, so check isAbstract() too before reusing it.
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
