<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\DependencyInjection\Compiler;

use IntegrationEngine\Bundle\Exception\IntegrationConfigurationException;
use IntegrationEngine\Core\Auth\DynamicAuthHandler;
use IntegrationEngine\Core\Contract\Client\ClientAdapterInterface;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Core\Registry\IntegrationRegistry;
use IntegrationEngine\Infrastructure\Adapter\YamlConfigAdapter;
use IntegrationEngine\Infrastructure\Http\ClientAdapterResolver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

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
}
