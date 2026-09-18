<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\DependencyInjection\Compiler;

use IntegrationEngine\Core\Contract\Client\ClientAdapterInterface;
use IntegrationEngine\Infrastructure\Http\ClientAdapterResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AdapterMapBuilder
{
    /** @return array<string, class-string<ClientAdapterInterface>> client type => adapter class */
    public function buildAdapterMap(ContainerBuilder $container): array
    {
        $resolverDefinition = $container->findDefinition(ClientAdapterResolver::class);

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

            $clientType = $class::getClientType();
            $adapterMap[$clientType] = $class;
            $resolverDefinition->addMethodCall('register', [$clientType, $class]);
        }

        return $adapterMap;
    }
}
