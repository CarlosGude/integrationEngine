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

        // Cargar servicios base del bundle
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );

        $loader->load('services.yaml');

        /*
         * 🔥 SOLO CONFIGURACIÓN
         * No lógica, no wiring, no decisiones.
         * Solo pasar datos al sistema Symfony.
         */
        $container->setParameter(
            'integration_engine.integrations',
            $config['integrations']
        );
    }
}
