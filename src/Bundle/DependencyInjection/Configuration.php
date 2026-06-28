<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\DependencyInjection;

use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('integration_engine');

        $treeBuilder->getRootNode()
            ->children()
                // ── integrations map ──────────────────────────────────────────
            ->arrayNode('integrations')
            ->defaultValue([])
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->children()
                        // ── action definitions ────────────────────────────────
            ->scalarNode('config_path')
            ->defaultNull()
            ->info('Absolute path to the YAML file defining the actions for this integration.')
            ->end()

                        // ── HTTP transport ────────────────────────────────────
            ->scalarNode('base_url')
            ->defaultNull()
            ->info('Base URL for the built-in SymfonyHttpClientAdapter. Required unless client_service is set.')
            ->end()
            ->scalarNode('client_service')
            ->defaultNull()
            ->info('Custom ClientInterface service ID. Overrides base_url and client if set.')
            ->end()
            ->scalarNode('client')
            ->defaultValue(SymfonyHttpClientAdapter::CLIENT_TYPE)
            ->info('Client type to use: "rest" (default) or "graphql". Ignored when client_service is set.')
            ->validate()
            ->ifTrue(static fn (mixed $v): bool => \is_scalar($v) && '' === trim((string) $v))
            ->thenInvalid('Client type cannot be empty.')
            ->end()
            ->end()

                        // ── cache ─────────────────────────────────────────────
            ->scalarNode('cache_service')
            ->defaultNull()
            ->info('Custom CachePort service ID. Defaults to InMemoryCacheAdapter.')
            ->end()

                        // ── middlewares ───────────────────────────────────────
            ->arrayNode('middlewares')
            ->info('Ordered list of middleware service IDs (outermost first). Only services tagged with integration_engine.middleware are accepted.')
            ->scalarPrototype()->end()
            ->defaultValue([])
            ->end()

                        // ── default headers ───────────────────────────────────
            ->arrayNode('headers')
            ->info('Default HTTP headers sent with every request for this integration. Auth headers are merged on top.')
            ->normalizeKeys(false)
            ->useAttributeAsKey('name')
            ->scalarPrototype()->end()
            ->defaultValue([])
            ->end()
            ->end()

                    // ── cross-field validation ────────────────────────────────
            ->validate()
            ->ifTrue(static function (mixed $v): bool {
                return \is_array($v) && null === $v['base_url'] && null === $v['client_service'];
            })
            ->thenInvalid('Each integration must define either "base_url" or "client_service".')
            ->end()
            ->end()
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
