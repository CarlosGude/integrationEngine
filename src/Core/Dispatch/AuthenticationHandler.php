<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Dispatch;

use IntegrationEngine\Core\Auth\DynamicAuthHandler;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Auth\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Contract\Client\ClientInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;
use IntegrationEngine\Core\Port\CachePort;
use IntegrationEngine\Core\Port\ConfigPort;
use Psr\Log\LoggerInterface;

final class AuthenticationHandler
{
    private DynamicAuthHandler $dynamicAuthHandler;

    public function __construct(
        ConfigPort $config,
        ClientInterface $client,
        CachePort $cache,
        string $integrationName,
        ?LoggerInterface $logger = null,
    ) {
        $this->dynamicAuthHandler = new DynamicAuthHandler($config, $client, $cache, $integrationName, $logger);
    }

    /**
     * @param \Closure(AbstractAction, array<mixed>, array<string, list<string>>): ResponseInterface $buildResponse
     */
    public function handle(
        AbstractAction $action,
        DynamicAuthorizationConfig $auth,
        ?ActionContextInterface $context,
        ?RequestHeadersInterface $headers,
        \Closure $buildResponse,
        ?ClientInterface $client = null,
        ?string $cacheDiscriminator = null,
    ): ResponseInterface {
        return $this->dynamicAuthHandler->handle(
            action: $action,
            auth: $auth,
            context: $context,
            headers: $headers,
            buildResponse: $buildResponse,
            client: $client,
            cacheDiscriminator: $cacheDiscriminator,
        );
    }

    public function withStaticToken(
        AbstractAction $action,
        DynamicAuthorizationConfig $auth,
        mixed $preloadedCache = null,
        ?ClientInterface $client = null,
        ?string $cacheDiscriminator = null,
    ): AbstractAction {
        return $this->dynamicAuthHandler->withStaticToken(
            action: $action,
            auth: $auth,
            preloadedCache: $preloadedCache,
            client: $client,
            cacheDiscriminator: $cacheDiscriminator,
        );
    }
}
