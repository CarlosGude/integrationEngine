<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class IntegrationEngineExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );

        $loader->load('services.yaml');

        /** @var array<string, array{config_path: null|string, base_url: null|string, client: string, client_service: null|string, cache_service: null|string, headers: array<string, string>}> $integrations */
        $integrations = $config['integrations'];

        $container->setParameter(
            'integration_engine.integrations',
            $integrations
        );
    }
}
