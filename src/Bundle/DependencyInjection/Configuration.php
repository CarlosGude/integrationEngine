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
            ->arrayNode('integrations')
            ->defaultValue([])
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->children()
            ->scalarNode('config_path')
            ->defaultNull()
            ->info('Absolute path to the YAML file defining the actions for this integration.')
            ->end()

            ->scalarNode('base_url')
            ->defaultNull()
            ->info('Base URL for bundle-managed client adapters. Required unless client_service is set.')
            ->end()
            ->scalarNode('client_service')
            ->defaultNull()
            ->info('Custom ClientInterface service ID. Overrides base_url and client if set.')
            ->end()
            ->scalarNode('client')
            ->defaultValue(SymfonyHttpClientAdapter::CLIENT_TYPE)
            ->info('Client type to use: "rest" (default), "graphql", or "form_encoded". Ignored when client_service is set.')
            ->validate()
            ->ifTrue(static fn (mixed $v): bool => \is_scalar($v) && '' === trim((string) $v))
            ->thenInvalid('Client type cannot be empty.')
            ->end()
            ->end()

            ->floatNode('timeout')->defaultNull()->min(0)->end()
            ->floatNode('max_duration')->defaultNull()->min(0)->end()
            ->booleanNode('block_private_networks')->defaultFalse()->end()
            ->arrayNode('allowed_hosts')
            ->scalarPrototype()
            ->validate()
            ->ifTrue(static fn (mixed $host): bool => !\is_string($host) || 1 !== preg_match('/^(?:\*\.)?[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/iD', $host))
            ->thenInvalid('allowed_hosts must contain host names, optionally prefixed by *. (no scheme, port or path).')
            ->end()
            ->end()
            ->defaultValue([])
            ->end()
            ->arrayNode('retry')
            ->treatNullLike([])
            ->children()
            ->integerNode('max_retries')->defaultValue(3)->min(1)->end()
            ->integerNode('delay_ms')->defaultValue(200)->min(0)->end()
            ->floatNode('multiplier')->defaultValue(2.0)->min(1)->end()
            ->integerNode('max_delay_ms')->defaultValue(2000)->min(0)->end()
            ->floatNode('jitter')->defaultValue(0.1)->min(0)->max(1)->end()
            ->arrayNode('status_codes')
            ->integerPrototype()->min(100)->max(599)->end()
            ->defaultValue([423, 425, 429, 500, 502, 503, 504, 507, 510])
            ->end()
            ->booleanNode('retry_non_idempotent')->defaultFalse()->end()
            ->end()
            ->end()

            ->scalarNode('cache_service')
            ->defaultNull()
            ->info('Custom CachePort service ID. Defaults to integration_engine.cache.default (PSR-6 over Symfony cache.app).')
            ->end()

            ->scalarNode('connection_resolver')
            ->defaultNull()
            ->info('Service ID implementing ConnectionResolverInterface, for integrations whose base_url/authorization vary per call via the $connection argument to send()/sendMany(). Optional — integrations that never pass $connection do not need one.')
            ->end()

            ->arrayNode('middlewares')
            ->info('Ordered list of middleware service IDs (outermost first). Only services tagged with integration_engine.middleware are accepted.')
            ->scalarPrototype()->end()
            ->defaultValue([])
            ->end()

            ->arrayNode('request_middlewares')
            ->info('Ordered list of RequestMiddlewareInterface service IDs (outermost first), run on the fully-built request just before the HTTP call — e.g. request signing (OAuth 1.0a). Only services tagged with integration_engine.request_middleware are accepted. Only applies to the built-in rest/graphql/form_encoded clients, not client_service.')
            ->scalarPrototype()->end()
            ->defaultValue([])
            ->end()

            ->arrayNode('headers')
            ->info('Default HTTP headers sent with every request for this integration. Auth headers are merged on top.')
            ->normalizeKeys(false)
            ->useAttributeAsKey('name')
            ->scalarPrototype()->end()
            ->defaultValue([])
            ->end()
            ->end()

            ->validate()
            ->ifTrue(static function (mixed $v): bool {
                return \is_array($v) && null === $v['base_url'] && null === $v['client_service'];
            })
            ->thenInvalid('Each integration must define either "base_url" or "client_service".')
            ->end()
            ->validate()
            ->ifTrue(static fn (mixed $v): bool => \is_array($v) && null !== $v['client_service'] && (isset($v['retry']) || null !== $v['timeout'] || null !== $v['max_duration'] || $v['block_private_networks']))
            ->thenInvalid('retry, timeout, max_duration and block_private_networks cannot be used with client_service: the engine does not control its transport.')
            ->end()
            ->end()
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
