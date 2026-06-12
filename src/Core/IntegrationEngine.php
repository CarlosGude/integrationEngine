<?php

declare(strict_types=1);

namespace IntegrationEngine\Core;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\ActionBodyInterface;
use IntegrationEngine\Core\Contract\ActionContextInterface;
use IntegrationEngine\Core\Contract\ClientInterface;
use IntegrationEngine\Core\Contract\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Contract\RequestHeadersInterface;
use IntegrationEngine\Core\Contract\ResponseInterface;
use IntegrationEngine\Core\Contract\StaticAuthorizationConfig;
use IntegrationEngine\Core\Exception\DynamicAuthException;
use IntegrationEngine\Core\Exception\MapperActionMismatchException;
use IntegrationEngine\Core\Exception\NotMappedActionException;
use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Core\Port\CachePort;
use IntegrationEngine\Core\Port\ConfigPort;
use IntegrationEngine\Core\Response\EmptyResponse;

final readonly class IntegrationEngine
{
    public function __construct(
        private ConfigPort $config,
        private ClientInterface $client,
        private CachePort $cache,
        private string $integrationName,
    ) {}

    public function send(
        string $actionName,
        ?ActionContextInterface $context = null,
        ?ActionBodyInterface $body = null,
        ?RequestHeadersInterface $headers = null,
    ): ResponseInterface {
        $action = $this->config->getAction($actionName, $body);
        $auth = $action->getAuthorization();

        if ($auth instanceof DynamicAuthorizationConfig) {
            return $this->sendWithDynamicAuth($action, $auth, $context, $headers);
        }

        $rawResponse = $this->client->send($action, $context, $headers);

        return $this->buildResponse($action, $rawResponse);
    }

    private function sendWithDynamicAuth(
        AbstractAction $action,
        DynamicAuthorizationConfig $auth,
        ?ActionContextInterface $context,
        ?RequestHeadersInterface $headers,
    ): ResponseInterface {
        $usedCachedToken = \is_string($this->cache->get($this->tokenCacheKey($auth)));

        $authorized = $this->withStaticToken($action, $auth);

        try {
            $rawResponse = $this->client->send($authorized, $context, $headers);
        } catch (RequestResponseException $e) {
            // A cached token can be revoked or expire server-side before its
            // TTL: drop it and retry once with a freshly fetched token. A
            // fresh token rejected with 401 is not retried — fetching it
            // again would yield the same result.
            if (401 !== $e->statusCode || !$usedCachedToken) {
                throw $e;
            }

            $this->cache->delete($this->tokenCacheKey($auth));

            $authorized = $this->withStaticToken($action, $auth);
            $rawResponse = $this->client->send($authorized, $context, $headers);
        }

        return $this->buildResponse($authorized, $rawResponse);
    }

    /**
     * @param array<mixed> $rawResponse
     */
    private function buildResponse(AbstractAction $action, array $rawResponse): ResponseInterface
    {
        if (!$action::hasResponse()) {
            return new EmptyResponse();
        }

        return $this->applyMapper($action, $rawResponse);
    }

    private function withStaticToken(
        AbstractAction $action,
        DynamicAuthorizationConfig $auth,
    ): AbstractAction {
        $token = $this->resolveToken($auth);

        $isDefaultHeader = 'Authorization' === $auth->header;

        return $action::create(
            method: $action->getMethod(),
            path: $action->getRawPath(),
            body: $action->getBody(),
            authorization: new StaticAuthorizationConfig(
                type: $isDefaultHeader ? 'bearer' : 'api_key',
                params: $isDefaultHeader
                    ? ['token' => $token, 'prefix' => $auth->resolvedPrefix()]
                    : ['header' => $auth->header, 'token' => $token, 'prefix' => $auth->resolvedPrefix()],
            ),
        );
    }

    private function tokenCacheKey(DynamicAuthorizationConfig $authConfig): string
    {
        return \sprintf('integration_engine.token.%s.%s', $this->integrationName, $authConfig->action);
    }

    private function resolveToken(DynamicAuthorizationConfig $authConfig): string
    {
        $cacheKey = $this->tokenCacheKey($authConfig);

        $cached = $this->cache->get($cacheKey);
        if (\is_string($cached)) {
            return $cached;
        }

        $authAction = $this->config->getAction($authConfig->action, null);
        $rawResponse = $this->client->send($authAction);

        $authResponse = $this->applyMapper($authAction, $rawResponse);
        $responseArray = $authResponse->toArray();

        if (!isset($responseArray[$authConfig->tokenField])) {
            throw DynamicAuthException::missingTokenField($authConfig->action, $authConfig->tokenField);
        }

        $tokenValue = $responseArray[$authConfig->tokenField];
        if (!\is_scalar($tokenValue)) {
            throw DynamicAuthException::nonScalarTokenField($authConfig->tokenField);
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

        // Fail-fast before entering the mapper: catches misconfigured actions
        // from custom ConfigPort or ClientInterface implementations.
        // AbstractMapper::map() carries the same guard as a public contract
        // for callers that use it outside the engine flow.
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
