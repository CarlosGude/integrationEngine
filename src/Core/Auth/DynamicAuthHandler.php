<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Auth;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Auth\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Contract\Client\ClientInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;
use IntegrationEngine\Core\Exception\DynamicAuthException;
use IntegrationEngine\Core\Exception\NotMappedActionException;
use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Core\Port\CachePort;
use IntegrationEngine\Core\Port\ConfigPort;
use Psr\Log\LoggerInterface;

final readonly class DynamicAuthHandler
{
    public function __construct(
        private ConfigPort $config,
        private ClientInterface $client,
        private CachePort $cache,
        private string $integrationName,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * @param \Closure(AbstractAction, array<mixed>): ResponseInterface $buildResponse
     */
    public function handle(
        AbstractAction $action,
        DynamicAuthorizationConfig $auth,
        ?ActionContextInterface $context,
        ?RequestHeadersInterface $headers,
        \Closure $buildResponse,
        ?ClientInterface $client = null,
    ): ResponseInterface {
        $client ??= $this->client;
        $cached = $this->cache->get($this->cacheKey($auth));
        $usedCachedToken = \is_string($cached);
        $authorized = $this->withStaticToken($action, $auth, $cached, $client);

        try {
            $rawResponse = $client->send($authorized, $context, $headers);
        } catch (RequestResponseException $e) {
            if (401 !== $e->statusCode || !$usedCachedToken) {
                throw $e;
            }

            $this->logger?->warning('Cached auth token rejected (401); retrying with a fresh token', [
                'integration' => $this->integrationName,
                'action' => $action::getName(),
                'token_action' => $auth->action,
            ]);

            $this->cache->delete($this->cacheKey($auth));
            $authorized = $this->withStaticToken($action, $auth, client: $client);
            $rawResponse = $client->send($authorized, $context, $headers);
        }

        return ($buildResponse)($authorized, $rawResponse);
    }

    public function withStaticToken(
        AbstractAction $action,
        DynamicAuthorizationConfig $auth,
        mixed $preloadedCache = null,
        ?ClientInterface $client = null,
    ): AbstractAction {
        $token = $this->resolveToken($auth, $preloadedCache, $client);

        return $action::create(
            method: $action->getMethod(),
            path: $action->getRawPath(),
            body: $action->getBody(),
            authorization: $auth->toStaticConfig($token),
        );
    }

    /**
     * @param array<mixed> $rawResponse
     *
     * @return array<mixed>
     */
    private function mapTokenResponse(AbstractAction $action, array $rawResponse): array
    {
        if (!$action::hasResponse()) {
            return $rawResponse;
        }

        $mapperClass = $action::mapper();

        if (null === $mapperClass) {
            throw new NotMappedActionException($action::getName());
        }

        return $mapperClass::map($action, $rawResponse)->toArray();
    }

    private function cacheKey(DynamicAuthorizationConfig $auth): string
    {
        return $auth->cacheKey($this->integrationName);
    }

    private function resolveToken(
        DynamicAuthorizationConfig $authConfig,
        mixed $preloadedCache = null,
        ?ClientInterface $client = null,
    ): string {
        $cacheKey = $this->cacheKey($authConfig);
        $cached = \is_string($preloadedCache) ? $preloadedCache : $this->cache->get($cacheKey);

        if (\is_string($cached)) {
            $this->logger?->debug('Auth token cache hit — skipping fetch', [
                'integration' => $this->integrationName,
                'token_action' => $authConfig->action,
            ]);

            return $cached;
        }

        $this->logger?->info('Fetching dynamic auth token', [
            'integration' => $this->integrationName,
            'token_action' => $authConfig->action,
        ]);

        $authAction = $this->config->getAction($authConfig->action, null);
        $rawResponse = ($client ?? $this->client)->send($authAction);
        $responseArray = $this->mapTokenResponse($authAction, $rawResponse);

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
}
