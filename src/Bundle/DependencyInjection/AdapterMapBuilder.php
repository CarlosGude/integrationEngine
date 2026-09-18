<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\DependencyInjection;

use IntegrationEngine\Core\Contract\Client\ClientAdapterInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AdapterMapBuilder
{
    public function buildAdapterMap(ContainerBuilder $container): array
    {
        $adapters = $container->findTaggedServiceIds('integration_engine.client_adapter');
        $map = [];

        foreach ($adapters as $serviceId => $tags) {
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

            foreach ($tags as $tag) {
                $type = $tag['type'] ?? null;
                if (null === $type) {
                    throw new \InvalidArgumentException(\sprintf(
                        'Service "%s" is tagged as "integration_engine.client_adapter" but does not specify a "type" attribute.',
                        $serviceId,
                    ));
                }

                $map[$type] = $class;
            }
        }

        return $map;
    }
}
