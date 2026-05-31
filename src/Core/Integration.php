<?php

declare(strict_types=1);

namespace IntegrationEngine\Core;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\AbstractMapper;
use IntegrationEngine\Core\Contract\ActionBodyInterface;
use IntegrationEngine\Core\Contract\ClientInterface;
use IntegrationEngine\Core\Contract\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Contract\ResponseInterface;
use IntegrationEngine\Core\Contract\StaticAuthorizationConfig;
use IntegrationEngine\Core\Exception\InvalidMapperException;
use IntegrationEngine\Core\Exception\MapperActionMismatchException;
use IntegrationEngine\Core\Port\CachePort;
use IntegrationEngine\Core\Port\ConfigPort;

final class Integration
{
    public function __construct(
        private readonly ConfigPort $config,
        private readonly ClientInterface $client,
        private readonly CachePort $cache,
    ) {
    }

    /**
     * @throws \InvalidArgumentException     if the action is not found
     * @throws InvalidMapperException        if the mapper class is invalid
     * @throws MapperActionMismatchException if the mapper does not belong to the action
     * @throws \RuntimeException             on HTTP errors
     */
    public function send(string $actionName, ?ActionBodyInterface $body = null): ResponseInterface
    {
        $action = $this->config->getAction($actionName, $body);

        if ($action->getAuthorization() instanceof DynamicAuthorizationConfig) {
            $token = $this->resolveToken($action->getAuthorization());

            $action = $action::create(
                method: $action->getMethod(),
                path: $action->getPath(),
                body: $action->getBody(),
                authorization: new StaticAuthorizationConfig(
                    type: 'bearer',
                    params: ['token' => $token],
                ),
            );
        }

        $rawResponse = $this->client->send($action);

        return $this->applyMapper($action, $rawResponse);
    }

    private function resolveToken(DynamicAuthorizationConfig $authConfig): string
    {
        $cacheKey = sprintf('integration_engine.token.%s', $authConfig->action);

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $authAction = $this->config->getAction($authConfig->action);
        $rawResponse = $this->client->send($authAction);
        $authResponse = $this->applyMapper($authAction, $rawResponse);

        $responseArray = $authResponse->toArray();

        if (!isset($responseArray[$authConfig->tokenField])) {
            throw new \RuntimeException(sprintf('Dynamic auth action "%s" response does not contain field "%s".', $authConfig->action, $authConfig->tokenField));
        }

        $token = $responseArray[$authConfig->tokenField];
        $this->cache->set($cacheKey, $token, $authConfig->ttl);

        return $token;
    }

    private function applyMapper(AbstractAction $action, array $rawResponse): ResponseInterface
    {
        $mapperClass = $action::getMapper();

        if (!is_a($mapperClass, AbstractMapper::class, allow_string: true)) {
            throw new InvalidMapperException($mapperClass);
        }

        if ($mapperClass::getAction() !== $action::class) {
            throw new MapperActionMismatchException(mapperClass: $mapperClass, expectedActionClass: $mapperClass::getAction(), actualActionClass: $action::class);
        }

        return $mapperClass::map($action, $rawResponse);
    }
}
