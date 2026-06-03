<?php

declare(strict_types=1);

namespace IntegrationEngine\Core;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\ActionBodyInterface;
use IntegrationEngine\Core\Contract\ActionContextInterface;
use IntegrationEngine\Core\Contract\ClientInterface;
use IntegrationEngine\Core\Contract\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Contract\ResponseInterface;
use IntegrationEngine\Core\Contract\StaticAuthorizationConfig;
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

        $staticAuth = new StaticAuthorizationConfig(
            type: $isDefaultHeader ? 'bearer' : 'api_key',
            params: $isDefaultHeader
                ? ['token' => $token]
                : ['header' => $auth->header, 'token' => $token],
        );

        return $action::create(
            method: $action->getMethod(),
            path: $action->getPath(),
            body: $action->getBody(),
            authorization: $staticAuth,
        )->withContext($context ?? $action->getActionContext());
    }

    private function resolveToken(DynamicAuthorizationConfig $authConfig): string
    {
        $cacheKey = \sprintf('integration_engine.token.%s', $authConfig->action);

        if ($this->cache->has($cacheKey)) {
            $cached = $this->cache->get($cacheKey);
            if (!\is_string($cached)) {
                throw new \RuntimeException(\sprintf('Cached token for "%s" is not a string.', $authConfig->action));
            }

            return $cached;
        }

        $authAction = $this->config->getAction($authConfig->action, null);
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

        $tokenValue = $responseArray[$authConfig->tokenField];
        if (!\is_scalar($tokenValue)) {
            throw new \RuntimeException(\sprintf('Token field "%s" must be a scalar value.', $authConfig->tokenField));
        }

        $token = (string) $tokenValue;

        $this->cache->set($cacheKey, $token, $authConfig->ttl);

        return $token;
    }

    /**
     * @param array<mixed> $rawResponse
     */
    private function applyMapper(AbstractAction $action, array $rawResponse): ResponseInterface
    {
        $mapperClass = $action::mapper();

        if (null === $mapperClass) {
            throw new NotMappedActionException($action::getName());
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
