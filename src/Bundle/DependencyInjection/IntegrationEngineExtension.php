<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\DependencyInjection;

use IntegrationEngine\Core\Integration;
use IntegrationEngine\Core\Port\CachePort;
use IntegrationEngine\Core\Registry\IntegrationRegistry;
use IntegrationEngine\Infrastructure\Adapter\YamlConfigAdapter;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

final class IntegrationEngineExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $registryDefinition = $container->getDefinition(IntegrationRegistry::class);

        foreach ($config['integrations'] as $name => $integrationConfig) {
            // Config adapter
            $configAdapterId = sprintf('integration_engine.config.%s', $name);
            $container->setDefinition($configAdapterId, new Definition(
                YamlConfigAdapter::class,
                [$integrationConfig['config_path']],
            ));

            // Client: custom service o built-in SymfonyHttpClientAdapter
            if (null !== $integrationConfig['client_service']) {
                $clientReference = new Reference($integrationConfig['client_service']);
            } else {
                $clientId = sprintf('integration_engine.http_client.%s', $name);
                $container->setDefinition($clientId, new Definition(
                    SymfonyHttpClientAdapter::class,
                    [new Reference('http_client'), $integrationConfig['base_url']],
                ));
                $clientReference = new Reference($clientId);
            }

            // Cache: custom service o default
            $cacheReference = new Reference($integrationConfig['cache_service'] ?? CachePort::class);

            // Integration
            $integrationId = sprintf('integration_engine.integration.%s', $name);
            $container->setDefinition($integrationId, new Definition(
                Integration::class,
                [$configAdapterId, $clientReference, $cacheReference],
            ));

            $registryDefinition->addMethodCall('register', [$name, new Reference($integrationId)]);
        }
    }
}
