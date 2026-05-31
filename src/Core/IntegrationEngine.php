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

final readonly class IntegrationEngine
{
    public function __construct(
        private ConfigPort $config,
        private ClientInterface $client,
        private CachePort $cache,
    ) {}

    /**
     * @throws \InvalidArgumentException
     * @throws InvalidMapperException
     * @throws MapperActionMismatchException
     * @throws \RuntimeException
     */
    public function send(string $actionName, ?ActionBodyInterface $body = null): ResponseInterface
    {
        $action = $this->config->getAction($actionName, $body);

        $action = $this->applyAuthorization($action);

        $rawResponse = $this->client->send($action);

        if (!$action::hasResponse()) {
            return $this->createEmptyResponse();
        }

        return $this->applyMapper($action, $rawResponse);
    }

    private function applyAuthorization(AbstractAction $action): AbstractAction
    {
        $auth = $action->getAuthorization();

        if (!$auth instanceof DynamicAuthorizationConfig) {
            return $action;
        }

        $token = $this->resolveToken($auth);

        return $action::create(
            method: $action->getMethod(),
            path: $action->getPath(),
            body: $action->getBody(),
            authorization: new StaticAuthorizationConfig(
                type: 'bearer',
                params: ['token' => $token],
            ),
        );
    }

    private function resolveToken(DynamicAuthorizationConfig $authConfig): string
    {
        $cacheKey = \sprintf('integration_engine.token.%s', $authConfig->action);

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $authAction = $this->config->getAction($authConfig->action);
        $rawResponse = $this->client->send($authAction);

        $authResponse = $this->applyMapper($authAction, $rawResponse);

        $responseArray = $authResponse->toArray();

        if (!isset($responseArray[$authConfig->tokenField])) {
            throw new \RuntimeException(\sprintf('Dynamic auth action "%s" response does not contain field "%s".', $authConfig->action, $authConfig->tokenField));
        }

        $token = $responseArray[$authConfig->tokenField];

        $this->cache->set($cacheKey, $token, $authConfig->ttl);

        return $token;
    }

    private function applyMapper(AbstractAction $action, array $rawResponse): ResponseInterface
    {
        $mapperClass = $action::mapper();

        if (null === $mapperClass) {
            throw new \LogicException(\sprintf('Action "%s" requires a mapper but none was defined.', $action::class));
        }

        if (!is_a($mapperClass, AbstractMapper::class, true)) {
            throw new InvalidMapperException($mapperClass);
        }

        if ($mapperClass::getAction() !== $action::class) {
            throw new MapperActionMismatchException(mapperClass: $mapperClass, expectedActionClass: $mapperClass::getAction(), actualActionClass: $action::class);
        }

        return $mapperClass::map($action, $rawResponse);
    }

    private function createEmptyResponse(): ResponseInterface
    {
        return new class implements ResponseInterface {
            public function toArray(): array
            {
                return [];
            }
        };
    }
}
