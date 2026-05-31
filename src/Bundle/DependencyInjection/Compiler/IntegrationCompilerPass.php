<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\DependencyInjection\Compiler;

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

        $integrations = $container->getParameter('integration_engine.integrations');

        $registry = $container->findDefinition('IntegrationEngine\Core\Registry\IntegrationRegistry');

        foreach ($integrations as $name => $config) {

            $configId = "integration_engine.config.$name";

            $container->setDefinition($configId, new Definition(
                \IntegrationEngine\Infrastructure\Adapter\YamlConfigAdapter::class,
                [$config['config_path']]
            ));

            $clientRef = $config['client_service']
                ? new Reference($config['client_service'])
                : new Reference('integration_engine.http_client.default');

            $cacheRef = new Reference(
                $config['cache_service'] ?? 'integration_engine.cache.default'
            );

            $integrationId = "integration_engine.integration.$name";

            $container->setDefinition($integrationId, new Definition(
                \IntegrationEngine\Core\Integration::class,
                [
                    new Reference($configId),
                    $clientRef,
                    $cacheRef,
                ]
            ));

            $registry->addMethodCall('register', [
                $name,
                new Reference($integrationId),
            ]);
        }
    }
}