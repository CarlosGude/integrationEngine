<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\DependencyInjection\Compiler;

use IntegrationEngine\Bundle\Exception\IntegrationConfigurationException;
use IntegrationEngine\Core\Contract\Client\ClientAdapterInterface;
use IntegrationEngine\Core\Dispatch\AuthenticationHandler;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Core\Registry\IntegrationRegistry;
use IntegrationEngine\Core\Security\HostPolicy;
use IntegrationEngine\Infrastructure\Adapter\FormEncodedClientAdapter;
use IntegrationEngine\Infrastructure\Adapter\YamlConfigAdapter;
use IntegrationEngine\Infrastructure\Cache\CachingMiddleware;
use IntegrationEngine\Infrastructure\Client\MiddlewareClient;
use IntegrationEngine\Infrastructure\Debug\IntegrationEngineDataCollector;
use IntegrationEngine\Infrastructure\Debug\TracingMiddleware;
use IntegrationEngine\Infrastructure\Http\GraphQLClientAdapter;
use IntegrationEngine\Infrastructure\Http\HostPolicyHttpClient;
use IntegrationEngine\Infrastructure\Http\RetryStrategyFactory;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/**
 * @phpstan-type IntegrationConfig array{
 *     config_path: null|string,
 *     base_url: null|string,
 *     client_service: null|string,
 *     client: string,
 *     cache_service: null|string,
 *     connection_resolver: null|string,
 *     middlewares: list<string>,
 *     request_middlewares: list<string>,
 *     headers: array<string, string>,
 *     timeout?: ?float,
 *     max_duration?: ?float,
 *     allowed_hosts?: list<string>,
 *     block_private_networks?: bool,
 *     retry?: array{max_retries: int, delay_ms: int, multiplier: float, max_delay_ms: int, jitter: float, status_codes: list<int>, retry_non_idempotent: bool},
 * }
 */
final class IntegrationCompilerPass implements CompilerPassInterface
{
    /** @param null|\Closure(string): bool $classExists */
    public function __construct(private readonly ?\Closure $classExists = null) {}

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('integration_engine.integrations')) {
            return;
        }

        /** @var array<string, IntegrationConfig> $integrations normalised by Configuration */
        $integrations = $container->getParameter('integration_engine.integrations');

        $middlewareResolver = new MiddlewareResolver();
        $registeredMiddlewares = $middlewareResolver->resolveTaggedMiddlewares($container);
        $registeredRequestMiddlewares = $middlewareResolver->resolveTaggedRequestMiddlewares($container);

        $adapterBuilder = new AdapterMapBuilder();
        $adapterMap = $adapterBuilder->buildAdapterMap($container);

        $wiring = new IntegrationWiringContext($middlewareResolver, $adapterMap, $registeredMiddlewares, $registeredRequestMiddlewares);

        $registry = $container->findDefinition(IntegrationRegistry::class);

        foreach ($integrations as $name => $config) {
            $this->wireIntegration($container, $registry, $name, $config, $wiring);
        }
    }

    /**
     * @param IntegrationConfig $config
     */
    private function wireIntegration(
        ContainerBuilder $container,
        Definition $registry,
        string $name,
        array $config,
        IntegrationWiringContext $wiring,
    ): void {
        if (null === $config['config_path']) {
            throw IntegrationConfigurationException::missingConfigPath($name);
        }

        $configId = "integration_engine.config.{$name}";
        $container->setDefinition($configId, new Definition(
            YamlConfigAdapter::class,
            [$config['config_path']],
        ));

        (new WebhookWiring($this->classExists ?? class_exists(...)))->register($container, $name, $config['config_path'], $configId);

        $cacheRef = new Reference(
            $config['cache_service'] ?? 'integration_engine.cache.default',
        );

        $loggerRef = new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE);

        $integrationRequestMiddlewares = $wiring->middlewareResolver->resolveIntegrationRequestMiddlewares($config['request_middlewares'], $wiring->registeredRequestMiddlewares, $name);
        $httpClientRef = $this->resolveHttpClientRef($container, $name, $config, $wiring->adapterMap, $integrationRequestMiddlewares);
        $integrationMiddlewares = $wiring->middlewareResolver->resolveIntegrationMiddlewares($config['middlewares'], $wiring->registeredMiddlewares, $name);
        $clientRef = $this->buildMiddlewareClient($container, $name, $httpClientRef, $cacheRef, $integrationMiddlewares);

        $authHandlerId = "integration_engine.auth_handler.{$name}";
        $container->setDefinition($authHandlerId, new Definition(
            AuthenticationHandler::class,
            [new Reference($configId), $clientRef, $cacheRef, $name, $loggerRef, new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE)],
        ));

        $connectionResolverRef = $config['connection_resolver'] ? new Reference($config['connection_resolver']) : null;

        $integrationId = "integration_engine.integration.{$name}";
        $container->setDefinition($integrationId, new Definition(
            IntegrationEngine::class,
            [
                new Reference($configId),
                $clientRef,
                $cacheRef,
                $name,
                $loggerRef,
                new Reference($authHandlerId),
                $connectionResolverRef,
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Definition(HostPolicy::class, [$config['allowed_hosts'] ?? []]),
                $config['base_url'],
            ],
        ));

        $registry->addMethodCall('register', [$name, new Reference($integrationId)]);
    }

    /**
     * Returns a Reference to the raw HTTP adapter for this integration.
     *
     * $requestMiddlewares is only passed through as a 4th constructor
     * argument for the three built-in adapter classes, whose shared
     * constructor shape (httpClient, baseUrl, defaultHeaders, requestMiddlewares)
     * this method already assumes. A custom adapter registered via
     * integration_engine.client_adapter with a different constructor isn't
     * touched — request_middlewares silently has no effect for it, since
     * such an adapter owns its own request construction and would need to
     * support RequestMiddlewareInterface itself.
     *
     * @param IntegrationConfig                                   $config
     * @param array<string, class-string<ClientAdapterInterface>> $adapterMap
     * @param list<string>                                        $requestMiddlewares
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
            $this->buildTransport($container, $name, $config),
            $config['base_url'],
            $config['headers'],
        ];

        if ([] !== $requestMiddlewares && \in_array($adapterClass, [SymfonyHttpClientAdapter::class, GraphQLClientAdapter::class, FormEncodedClientAdapter::class], true)) {
            $args[] = array_map(static fn (string $id): Reference => new Reference($id), $requestMiddlewares);
        }

        $container->setDefinition($httpClientId, new Definition($adapterClass, $args));

        return new Reference($httpClientId);
    }

    /** @param IntegrationConfig $config */
    private function buildTransport(ContainerBuilder $container, string $name, array $config): Reference
    {
        $ref = new Reference('http_client');
        $options = [];
        foreach (['timeout', 'max_duration'] as $option) {
            if (isset($config[$option])) {
                $options[$option] = $config[$option];
            }
        }
        if ([] !== $options) {
            $id = "integration_engine.transport.{$name}.base";
            $container->setDefinition($id, (new Definition())->setFactory([$ref, 'withOptions'])->setArguments([$options]));
            $ref = new Reference($id);
        }
        if ($config['block_private_networks'] ?? false) {
            $id = "integration_engine.transport.{$name}.private_networks";
            $container->setDefinition($id, new Definition(NoPrivateNetworkHttpClient::class, [$ref]));
            $ref = new Reference($id);
        }
        if ([] !== ($config['allowed_hosts'] ?? [])) {
            $id = "integration_engine.transport.{$name}.hosts";
            $container->setDefinition($id, new Definition(HostPolicyHttpClient::class, [$ref, new Definition(HostPolicy::class, [$config['allowed_hosts']])]));
            $ref = new Reference($id);
        }
        if (isset($config['retry'])) {
            $strategy = (new Definition())->setFactory([RetryStrategyFactory::class, 'create'])->setArguments([$config['retry']]);
            $id = "integration_engine.transport.{$name}";
            $container->setDefinition($id, new Definition(RetryableHttpClient::class, [$ref, $strategy, $config['retry']['max_retries']]));
            $ref = new Reference($id);
        }

        return $ref;
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
