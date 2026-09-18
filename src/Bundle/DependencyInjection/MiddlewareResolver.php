<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\DependencyInjection;

use IntegrationEngine\Core\Contract\Client\AbstractClientMiddleware;
use IntegrationEngine\Core\Contract\Client\RequestMiddlewareInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class MiddlewareResolver
{
    public function resolveTaggedMiddlewares(ContainerBuilder $container): array
    {
        $tagged = $container->findTaggedServiceIds('integration_engine.middleware');
        $registered = [];

        foreach ($tagged as $serviceId => $tags) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;

            if (!class_exists($class)) {
                throw new \InvalidArgumentException(\sprintf(
                    'Service "%s" is tagged as "integration_engine.middleware" but its class "%s" does not exist.',
                    $serviceId,
                    $class,
                ));
            }

            if (!is_a($class, AbstractClientMiddleware::class, true)) {
                throw new \InvalidArgumentException(\sprintf(
                    'Service "%s" (%s) is tagged as "integration_engine.middleware" but does not extend %s.',
                    $serviceId,
                    $class,
                    AbstractClientMiddleware::class,
                ));
            }

            $registered[$serviceId] = true;
        }

        return $registered;
    }

    public function resolveIntegrationMiddlewares(array $declared, array $registered, string $integrationName): array
    {
        $resolved = [];

        foreach ($declared as $serviceId) {
            if (!isset($registered[$serviceId])) {
                throw new \InvalidArgumentException(\sprintf(
                    'Integration "%s" declares middleware "%s" in its "middlewares" config, but it is not tagged with "integration_engine.middleware".',
                    $integrationName,
                    $serviceId,
                ));
            }

            $resolved[] = $serviceId;
        }

        return $resolved;
    }

    public function resolveTaggedRequestMiddlewares(ContainerBuilder $container): array
    {
        $tagged = $container->findTaggedServiceIds('integration_engine.request_middleware');
        $registered = [];

        foreach ($tagged as $serviceId => $tags) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;

            if (!class_exists($class)) {
                throw new \InvalidArgumentException(\sprintf(
                    'Service "%s" is tagged as "integration_engine.request_middleware" but its class "%s" does not exist.',
                    $serviceId,
                    $class,
                ));
            }

            if (!is_a($class, RequestMiddlewareInterface::class, true)) {
                throw new \InvalidArgumentException(\sprintf(
                    'Service "%s" (%s) is tagged as "integration_engine.request_middleware" but does not implement %s.',
                    $serviceId,
                    $class,
                    RequestMiddlewareInterface::class,
                ));
            }

            $registered[$serviceId] = true;
        }

        return $registered;
    }

    public function resolveIntegrationRequestMiddlewares(array $declared, array $registered, string $integrationName): array
    {
        $resolved = [];

        foreach ($declared as $serviceId) {
            if (!isset($registered[$serviceId])) {
                throw new \InvalidArgumentException(\sprintf(
                    'Integration "%s" declares request middleware "%s" in its "request_middlewares" config, but it is not tagged with "integration_engine.request_middleware".',
                    $integrationName,
                    $serviceId,
                ));
            }

            $resolved[] = $serviceId;
        }

        return $resolved;
    }
}
