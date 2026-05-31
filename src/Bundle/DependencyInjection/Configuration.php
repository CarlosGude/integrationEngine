<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('integration_engine');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('integrations')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('config_path')
                                ->isRequired()
                                ->cannotBeEmpty()
                                ->info('Absolute path to the YAML file defining the actions.')
                            ->end()
                            ->scalarNode('base_url')
                                ->defaultNull()
                                ->info('Base URL for the built-in SymfonyHttpClientAdapter. Required unless client_service is set.')
                            ->end()
                            ->scalarNode('client_service')
                                ->defaultNull()
                                ->info('Custom ClientInterface service ID. Overrides base_url if set.')
                            ->end()
                            ->scalarNode('cache_service')
                                ->defaultNull()
                                ->info('Custom CachePort service ID. Defaults to InMemoryCacheAdapter.')
                            ->end()
                        ->end()
                        ->validate()
                            ->ifTrue(fn($v) => $v['base_url'] === null && $v['client_service'] === null)
                            ->thenInvalid('Each integration must define either "base_url" or "client_service".')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
