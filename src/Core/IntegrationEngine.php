<?php

declare(strict_types=1);

namespace IntegrationEngine\Core;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\AbstractMapper;
use IntegrationEngine\Core\Contract\ActionBodyInterface;
use IntegrationEngine\Core\Contract\ActionContextInterface;
use IntegrationEngine\Core\Contract\ClientInterface;
use IntegrationEngine\Core\Contract\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Contract\ResponseInterface;
use IntegrationEngine\Core\Contract\StaticAuthorizationConfig;
use IntegrationEngine\Core\Exception\InvalidMapperException;
use IntegrationEngine\Core\Exception\MapperActionMismatchException;
use IntegrationEngine\Core\Exception\NotMappedActionException;
use IntegrationEngine\Core\Port\CachePort;
use IntegrationEngine\Core\Port\ConfigPort;
use IntegrationEngine\Core\Response\EmptyResponse;

final readonly class IntegrationEngine
{
    public function __construct(
        private ConfigPort $config,
        private ClientInterface $client,
        private CachePort $cache,
    ) {}

    public function send(
        string $actionName,
        ?ActionContextInterface $context = null,
        ?ActionBodyInterface $body = null,
    ): ResponseInterface {
        $action = $this->config->getAction($actionName, $body);

        $action = $this->applyContext($action, $context);

        $action = $this->applyAuthorization($action, $context);

        $rawResponse = $this->client->send($action);

        if (!$action::hasResponse()) {
            return new EmptyResponse();
        }

        return $this->applyMapper($action, $rawResponse);
    }

    private function applyContext(AbstractAction $action, ?ActionContextInterface $context): AbstractAction
    {
        if (empty($context)) {
            return $action;
        }

        return $action->withContext($context);
    }

    private function applyAuthorization(AbstractAction $action, ?ActionContextInterface $context = null): AbstractAction
    {
        $auth = $action->getAuthorization();

        if (!$auth instanceof DynamicAuthorizationConfig) {
            return $action;
        }

        $token = $this->resolveToken($auth);

        $isDefaultHeader = 'Authorization' === $auth->header;

        return $action::create(
            method: $action->getMethod(),
            path: $action->getPath(),
            body: $action->getBody(),
            authorization: new StaticAuthorizationConfig(
                type: $isDefaultHeader ? 'bearer' : 'api_key',
                params: $isDefaultHeader
                    ? ['token' => $token]
                    : ['header' => $auth->header, 'token' => $token],
            ),
        )->withContext($context);
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
            throw new \RuntimeException(\sprintf(
                'Dynamic auth action "%s" response does not contain field "%s".',
                $authConfig->action,
                $authConfig->tokenField
            ));
        }

        $token = $responseArray[$authConfig->tokenField];

        $this->cache->set($cacheKey, $token, $authConfig->ttl);

        return $token;
    }

    private function applyMapper(AbstractAction $action, array $rawResponse): ResponseInterface
    {
        $mapperClass = $action::mapper();

        if (null === $mapperClass) {
            throw new NotMappedActionException($action::getName());
        }

        if (!is_a($mapperClass, AbstractMapper::class, true)) {
            throw new InvalidMapperException($mapperClass);
        }

        if ($mapperClass::getAction() !== $action::class) {
            throw new MapperActionMismatchException(
                mapperClass: $mapperClass,
                expectedActionClass: $mapperClass::getAction(),
                actualActionClass: $action::class
            );
        }

        return $mapperClass::map($action, $rawResponse);
    }
}
