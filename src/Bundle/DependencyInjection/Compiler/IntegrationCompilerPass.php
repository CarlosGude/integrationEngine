<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\DependencyInjection\Compiler;

use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Infrastructure\Adapter\YamlConfigAdapter;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
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
            $configId = "integration_engine.config.{$name}";

            $container->setDefinition($configId, new Definition(
                YamlConfigAdapter::class,
                [$config['config_path']]
            ));

            if ($config['client_service']) {
                $clientRef = new Reference($config['client_service']);
            } else {
                $httpClientId = "integration_engine.http_client.{$name}";

                $container->setDefinition($httpClientId, new Definition(
                    SymfonyHttpClientAdapter::class,
                    [
                        new Reference('http_client'),
                        $config['base_url'],
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
                ]
            ));

            $registry->addMethodCall('register', [
                $name,
                new Reference($integrationId),
            ]);
        }
    }
}