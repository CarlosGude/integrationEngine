<?php

declare(strict_types=1);

namespace IntegrationEngine\Core;

use IntegrationEngine\Core\Batch\BatchResult;
use IntegrationEngine\Core\Batch\BatchResultCollection;
use IntegrationEngine\Core\Batch\BatchTokenRetry;
use IntegrationEngine\Core\Batch\EngineRequest;
use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionBodyInterface;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Auth\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Contract\Client\ClientInterface;
use IntegrationEngine\Core\Contract\Client\DynamicBaseUrlClientInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use IntegrationEngine\Core\Contract\Connection\ConnectionResolverInterface;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;
use IntegrationEngine\Core\Dispatch\AuthenticationHandler;
use IntegrationEngine\Core\Dispatch\BatchDispatcher;
use IntegrationEngine\Core\Dispatch\ConnectionResolver;
use IntegrationEngine\Core\Dispatch\ResponseBuilder;
use IntegrationEngine\Core\Event\RequestSent;
use IntegrationEngine\Core\Event\RequestFailed;
use IntegrationEngine\Core\Event\ResponseMapped;
use IntegrationEngine\Core\Exception\RequestResponseException;
use Psr\EventDispatcher\EventDispatcherInterface;
use IntegrationEngine\Core\Port\CachePort;
use IntegrationEngine\Core\Port\ConfigPort;
use Psr\Log\LoggerInterface;
use IntegrationEngine\Core\Security\HostPolicy;

final readonly class IntegrationEngine
{
    private AuthenticationHandler $authHandler;
    private ConnectionResolver $dispatchConnectionResolver;
    private ResponseBuilder $responseBuilder;
    private BatchDispatcher $batchDispatcher;

    public function __construct(
        private ConfigPort $config,
        private ClientInterface $client,
        private CachePort $cache,
        private string $integrationName,
        ?LoggerInterface $logger = null,
        ?AuthenticationHandler $authHandler = null,
        ?ConnectionResolverInterface $connectionResolver = null,
        private ?EventDispatcherInterface $eventDispatcher = null,
        private ?HostPolicy $hostPolicy = null,
        private ?string $baseUrl = null,
    ) {
        $this->authHandler = $authHandler ?? new AuthenticationHandler($config, $client, $cache, $integrationName, $logger, $eventDispatcher);
        $this->dispatchConnectionResolver = new ConnectionResolver($integrationName, $connectionResolver);
        $this->responseBuilder = new ResponseBuilder();
        $this->batchDispatcher = new BatchDispatcher($client, $integrationName, $logger);
    }

    /**
     * $connection is opaque runtime info the integration's own
     * ConnectionResolverInterface (configured via `connection_resolver` in
     * the bundle) turns into a base_url/authorization override — e.g. a
     * tenant id looked up against the application's own connection store.
     * Leave it null for integrations that don't vary by runtime connection.
     *
     * Dynamic-auth token caching is namespaced by, in priority order: the
     * resolved connectionId, then $connection itself when it's a scalar,
     * then the resolved base URL. This matters when several connections
     * share one base_url and the resolver doesn't set connectionId — $connection
     * (e.g. a tenant id) is what keeps their tokens from colliding; without
     * it (a non-scalar $connection and no connectionId/baseUrl) two such
     * connections would share one cache entry.
     */
    public function send(
        string $actionName,
        ?ActionContextInterface $context = null,
        ?ActionBodyInterface $body = null,
        ?RequestHeadersInterface $headers = null,
        ?string $baseUrl = null,
        mixed $connection = null,
    ): ResponseInterface {
        $startTime = microtime(true);
        $started = false;
        try {
            $action = $this->config->getAction($actionName, $body);
            $resolved = $this->dispatchConnectionResolver->resolveForDispatch($action, $connection, $baseUrl);
            $action = $resolved['action'];
            $resolvedBaseUrl = $resolved['baseUrl'] ?? $this->baseUrl;
            $this->emitStarted($actionName, $action, $startTime, $resolved['connectionId']);
            $started = true;
            $this->hostPolicy?->assertAllowed(($resolvedBaseUrl ?? '').$action->getPath($context));
            $cacheDiscriminator = $resolved['cacheDiscriminator'];
            $client = $this->resolveClient($resolvedBaseUrl);
            $auth = $action->getAuthorization();
            $statusCode = 0;
            if ($auth instanceof DynamicAuthorizationConfig) {
                $response = $this->authHandler->handle(
                    action: $action,
                    auth: $auth,
                    context: $context,
                    headers: $headers,
                    buildResponse: function (AbstractAction $a, array $respBody, array $respHeaders, int $status = 0) use (&$statusCode): ResponseInterface {
                        $statusCode = $status;

                        return $this->responseBuilder->build($a, $respBody, $respHeaders);
                    },
                    client: $client,
                    cacheDiscriminator: $cacheDiscriminator,
                );
            } else {
                $rawResponse = $client->send($action, $context, $headers);
                $statusCode = $rawResponse['statusCode'] ?? 0;
                $response = $this->responseBuilder->build($action, $rawResponse['body'], $rawResponse['headers']);
            }
        } catch (\Throwable $e) {
            if (!$started) {
                $this->emitStarted($actionName, null, $startTime);
            }
            $this->emitFailed($actionName, $e, $startTime);
            throw $e;
        }

        $this->emitMapped($actionName, $response, $statusCode, $startTime);

        return $response;
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
     */
    public function sendMany(array $requests): BatchResultCollection
    {
        $failures = [];
        $prepared = [];
        $tokenRetry = new BatchTokenRetry($this->cache, $this->integrationName);
        $connectionCache = [];
        $startedAt = [];

        foreach ($requests as $key => $request) {
            $startedAt[$key] = microtime(true);
            $started = false;
            try {
                $action = $this->config->getAction($request->actionName, $request->body);
                $resolved = $this->dispatchConnectionResolver->resolveForDispatch($action, $request->connection, $request->baseUrl, $connectionCache);
                $action = $resolved['action'];
                $baseUrl = $resolved['baseUrl'] ?? $this->baseUrl;
                $this->emitStarted($request->actionName, $action, $startedAt[$key], $resolved['connectionId'], $key);
                $started = true;
                $this->hostPolicy?->assertAllowed(($baseUrl ?? '').$action->getPath($request->context));
                $cacheDiscriminator = $resolved['cacheDiscriminator'];
                $client = $this->resolveClient($baseUrl);

                $auth = $action->getAuthorization();

                if ($auth instanceof DynamicAuthorizationConfig) {
                    $action = $tokenRetry->prepareWithToken(
                        $key,
                        $auth,
                        $cacheDiscriminator,
                        fn (): AbstractAction => $this->authHandler->withStaticToken($action, $auth, client: $client, cacheDiscriminator: $cacheDiscriminator, requestKey: $key),
                    );
                }

                $prepared[$key] = new PreparedRequest($action, $request->context, $request->headers, $baseUrl, $cacheDiscriminator);
            } catch (\Throwable $e) {
                if (!$started) {
                    $this->emitStarted($request->actionName, null, $startedAt[$key], requestKey: $key);
                }
                $failures[$key] = $e;
            }
        }

        $raw = $this->batchDispatcher->dispatch($prepared);
        $raw = $this->batchDispatcher->retry(
            $raw,
            $tokenRetry->plan($raw),
            fn (int|string $key, PreparedRequest $original, DynamicAuthorizationConfig $auth): PreparedRequest => new PreparedRequest(
                $this->authHandler->withStaticToken($original->action, $auth, client: $this->resolveClient($original->baseUrl), cacheDiscriminator: $original->cacheDiscriminator, refreshReason: 'rejected_401', requestKey: $key),
                $original->context,
                $original->headers,
                $original->baseUrl,
                $original->cacheDiscriminator,
            ),
            $prepared,
        );

        $results = [];

        foreach ($requests as $key => $request) {
            if (isset($failures[$key])) {
                $this->emitFailed($request->actionName, $failures[$key], $startedAt[$key], $key);
                $results[$key] = BatchResult::failure($failures[$key]);

                continue;
            }

            $rawResult = $raw[$key] ?? new \UnexpectedValueException(
                \sprintf('Batch client returned no result for request "%s".', $key)
            );

            if ($rawResult instanceof \Throwable) {
                $this->emitFailed($request->actionName, $rawResult, $startedAt[$key], $key);
                $results[$key] = BatchResult::failure($rawResult);

                continue;
            }

            try {
                $response = $this->responseBuilder->build($prepared[$key]->action, $rawResult['body'], $rawResult['headers']);
                $results[$key] = BatchResult::success($response);
                $this->emitMapped($request->actionName, $response, $rawResult['statusCode'] ?? 0, $startedAt[$key], $key);
            } catch (\Throwable $e) {
                $this->emitFailed($request->actionName, $e, $startedAt[$key], $key);
                $results[$key] = BatchResult::failure($e);
            }
        }

        $actionClasses = [];
        foreach ($prepared as $key => $preparedRequest) {
            $actionClasses[$key] = $preparedRequest->action::class;
        }

        return new BatchResultCollection($results, $actionClasses);
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

    private function emitStarted(string $actionName, ?AbstractAction $action, float $start, ?string $connectionId = null, int|string|null $requestKey = null): void
    {
        $this->eventDispatcher?->dispatch(new RequestSent($this->integrationName, $actionName, $action?->getMethod() ?? '', $action?->getRawPath() ?? '', $start, $connectionId, $requestKey));
    }

    private function emitMapped(string $actionName, ResponseInterface $response, int $statusCode, float $start, int|string|null $requestKey = null): void
    {
        $this->eventDispatcher?->dispatch(new ResponseMapped($this->integrationName, $actionName, (microtime(true) - $start) * 1000, $statusCode, $response::class, microtime(true), $requestKey));
    }

    private function emitFailed(string $actionName, \Throwable $error, float $start, int|string|null $requestKey = null): void
    {
        $statusCode = $error instanceof RequestResponseException ? $error->statusCode : 0;
        $message = $statusCode > 0 ? 'Upstream request failed.' : 'Integration request failed.';
        $this->eventDispatcher?->dispatch(new RequestFailed($this->integrationName, $actionName, (microtime(true) - $start) * 1000, $statusCode, $error::class, $message, microtime(true), $requestKey));
    }

    private function resolveClient(?string $baseUrl): ClientInterface
    {
        return (null !== $baseUrl && $this->client instanceof DynamicBaseUrlClientInterface)
            ? $this->client->withBaseUrl($baseUrl)
            : $this->client;
    }
}
