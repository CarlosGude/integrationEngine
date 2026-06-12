<?php

declare(strict_types=1);

namespace IntegrationEngine\Core;

use IntegrationEngine\Core\Batch\BatchResult;
use IntegrationEngine\Core\Batch\EngineRequest;
use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\ActionBodyInterface;
use IntegrationEngine\Core\Contract\ActionContextInterface;
use IntegrationEngine\Core\Contract\BatchClientInterface;
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

    /**
     * Sends all requests as one batch and returns one BatchResult per input
     * key, preserving keys and order. An individual failure never aborts the
     * batch — each key resolves to a success or a failure result.
     *
     * Requests run concurrently when the client implements
     * BatchClientInterface; otherwise they fall back to sequential sends.
     *
     * @param array<array-key, EngineRequest> $requests
     *
     * @return array<array-key, BatchResult>
     */
    public function sendMany(array $requests): array
    {
        $failures = [];
        $prepared = [];
        $retryableAuth = [];
        $fetchedInBatch = [];

        foreach ($requests as $key => $request) {
            try {
                $action = $this->config->getAction($request->actionName, $request->body);
                $auth = $action->getAuthorization();

                if ($auth instanceof DynamicAuthorizationConfig) {
                    // Same 401-retry semantics as send(), evaluated per batch:
                    // only tokens cached before this batch are retried. A token
                    // fetched while preparing the batch is fresh for every item
                    // that uses it, even if later items see it in the cache.
                    $cacheKey = $this->tokenCacheKey($auth);
                    if (\is_string($this->cache->get($cacheKey))) {
                        if (!\in_array($cacheKey, $fetchedInBatch, true)) {
                            $retryableAuth[$key] = $auth;
                        }
                    } else {
                        $fetchedInBatch[] = $cacheKey;
                    }

                    $action = $this->withStaticToken($action, $auth);
                }

                $prepared[$key] = new PreparedRequest($action, $request->context, $request->headers);
            } catch (\Throwable $e) {
                $failures[$key] = $e;
            }
        }

        $raw = $this->dispatchBatch($prepared);
        $raw = $this->retryCachedToken401s($requests, $raw, $retryableAuth);

        $results = [];

        foreach ($requests as $key => $request) {
            if (isset($failures[$key])) {
                $results[$key] = BatchResult::failure($failures[$key]);

                continue;
            }

            $rawResult = $raw[$key] ?? new \UnexpectedValueException(
                \sprintf('Batch client returned no result for request "%s".', $key)
            );

            if ($rawResult instanceof \Throwable) {
                $results[$key] = BatchResult::failure($rawResult);

                continue;
            }

            try {
                $results[$key] = BatchResult::success($this->buildResponse($prepared[$key]->action, $rawResult));
            } catch (\Throwable $e) {
                $results[$key] = BatchResult::failure($e);
            }
        }

        return $results;
    }

    /**
     * Like sendMany() but unwraps the results: returns the mapped responses
     * keyed like the input, or throws the first failure in request order.
     * The whole batch is dispatched before failures are evaluated, so all
     * requests are executed even when one of them fails.
     *
     * @param array<array-key, EngineRequest> $requests
     *
     * @return array<array-key, ResponseInterface>
     *
     * @throws \Throwable the first failed request's error, in request order
     */
    public function sendManyOrFail(array $requests): array
    {
        $responses = [];

        foreach ($this->sendMany($requests) as $key => $result) {
            $responses[$key] = $result->response();
        }

        return $responses;
    }

    /**
     * @param array<array-key, PreparedRequest> $prepared
     *
     * @return array<array-key, array<mixed>|\Throwable>
     */
    private function dispatchBatch(array $prepared): array
    {
        if ([] === $prepared) {
            return [];
        }

        if ($this->client instanceof BatchClientInterface) {
            return $this->client->sendMany($prepared);
        }

        $raw = [];

        foreach ($prepared as $key => $request) {
            try {
                $raw[$key] = $this->client->send($request->action, $request->context, $request->headers);
            } catch (\Throwable $e) {
                $raw[$key] = $e;
            }
        }

        return $raw;
    }

    /**
     * Retries, once and with a fresh token, every item rejected with HTTP 401
     * that entered the batch holding a cached token. Each rejected token is
     * dropped before re-resolving, so all retried items sharing a token
     * action reuse a single freshly fetched replacement.
     *
     * @param array<array-key, EngineRequest>              $requests
     * @param array<array-key, array<mixed>|\Throwable>    $raw
     * @param array<array-key, DynamicAuthorizationConfig> $retryableAuth
     *
     * @return array<array-key, array<mixed>|\Throwable>
     */
    private function retryCachedToken401s(array $requests, array $raw, array $retryableAuth): array
    {
        $toRetry = [];

        foreach ($retryableAuth as $key => $auth) {
            $result = $raw[$key] ?? null;

            if ($result instanceof RequestResponseException && 401 === $result->statusCode) {
                $toRetry[$key] = $auth;
            }
        }

        foreach ($toRetry as $auth) {
            $this->cache->delete($this->tokenCacheKey($auth));
        }

        $retryPrepared = [];

        foreach ($toRetry as $key => $auth) {
            $request = $requests[$key];

            try {
                $action = $this->config->getAction($request->actionName, $request->body);
                $retryPrepared[$key] = new PreparedRequest($this->withStaticToken($action, $auth), $request->context, $request->headers);
            } catch (\Throwable $e) {
                $raw[$key] = $e;
            }
        }

        foreach ($this->dispatchBatch($retryPrepared) as $key => $result) {
            $raw[$key] = $result;
        }

        return $raw;
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
