<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\DependencyInjection\Compiler;

use IntegrationEngine\Core\Contract\ClientAdapterInterface;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Core\Registry\IntegrationRegistry;
use IntegrationEngine\Infrastructure\Adapter\YamlConfigAdapter;
use IntegrationEngine\Infrastructure\Http\ClientAdapterResolver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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

        // ── Build adapter map from tagged services ─────────────────────────
        // Bundle built-ins have priority 0. Project adapters registered via
        // _instanceof also get priority 0 but are processed after bundle
        // services, so they naturally override built-ins for the same type.
        $resolverDefinition = $container->findDefinition(ClientAdapterResolver::class);

        /** @var array<string, class-string<ClientAdapterInterface>> $adapterMap */
        $adapterMap = [];

        foreach ($container->findTaggedServiceIds('integration_engine.client_adapter') as $serviceId => $tags) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;

            if (!class_exists($class)) {
                continue;
            }

            if (!is_a($class, ClientAdapterInterface::class, true)) {
                continue;
            }

            $adapterMap[$class::getClientType()] = $class;
            $resolverDefinition->addMethodCall('register', [$class::getClientType(), $class]);
        }

        // ── Wire integrations ──────────────────────────────────────────────
        $registry = $container->findDefinition(IntegrationRegistry::class);

        foreach ($integrations as $name => $config) {
            $configId = "integration_engine.config.{$name}";

            if (null === $config['config_path']) {
                throw new \InvalidArgumentException(\sprintf(
                    'Integration "%s" must define "config_path". '
                    .'Use "php bin/console make:integration" to generate it automatically.',
                    $name,
                ));
            }

            $container->setDefinition($configId, new Definition(
                YamlConfigAdapter::class,
                [$config['config_path']]
            ));

            if ($config['client_service']) {
                $clientRef = new Reference($config['client_service']);
            } else {
                $httpClientId = "integration_engine.http_client.{$name}";

                if (!isset($adapterMap[$config['client']])) {
                    throw new \InvalidArgumentException(\sprintf(
                        'Unknown client type "%s" for integration "%s". Registered types: %s.',
                        $config['client'],
                        $name,
                        implode(', ', array_keys($adapterMap)) ?: 'none',
                    ));
                }

                $adapterClass = $adapterMap[$config['client']];

                $container->setDefinition($httpClientId, new Definition(
                    $adapterClass,
                    [
                        new Reference('http_client'),
                        $config['base_url'],
                        $config['headers'],
                    ]
                ));

                $clientRef = new Reference($httpClientId);
            }

            $cacheRef = new Reference(
                $config['cache_service'] ?? 'integration_engine.cache.default'
            );

            $integrationId = "integration_engine.integration.{$name}";

            $container->setDefinition($integrationId, new Definition(
                IntegrationEngine::class,
                [
                    new Reference($configId),
                    $clientRef,
                    $cacheRef,
                    $name,
                ]
            ));

            $registry->addMethodCall('register', [
                $name,
                new Reference($integrationId),
            ]);
        }
    }
}
