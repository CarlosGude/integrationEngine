<?php

declare(strict_types=1);

namespace IntegrationEngine\Core;

use IntegrationEngine\Core\Auth\DynamicAuthHandler;
use IntegrationEngine\Core\Batch\BatchResult;
use IntegrationEngine\Core\Batch\BatchResultCollection;
use IntegrationEngine\Core\Batch\BatchTokenRetry;
use IntegrationEngine\Core\Batch\EngineRequest;
use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionBodyInterface;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Auth\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Contract\Client\BatchClientInterface;
use IntegrationEngine\Core\Contract\Client\ClientInterface;
use IntegrationEngine\Core\Contract\Client\DynamicBaseUrlClientInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use IntegrationEngine\Core\Contract\Connection\ConnectionCredentials;
use IntegrationEngine\Core\Contract\Connection\ConnectionResolverInterface;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;
use IntegrationEngine\Core\Exception\ConnectionResolutionException;
use IntegrationEngine\Core\Exception\MapperActionMismatchException;
use IntegrationEngine\Core\Exception\NotMappedActionException;
use IntegrationEngine\Core\Lifecycle\ActionCompleted;
use IntegrationEngine\Core\Lifecycle\ActionFailed;
use IntegrationEngine\Core\Lifecycle\ActionStarted;
use IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher;
use IntegrationEngine\Core\Port\CachePort;
use IntegrationEngine\Core\Port\ConfigPort;
use IntegrationEngine\Core\Response\EmptyResponse;
use Psr\Log\LoggerInterface;

final readonly class IntegrationEngine
{
    private DynamicAuthHandler $authHandler;

    public function __construct(
        private ConfigPort $config,
        private ClientInterface $client,
        private CachePort $cache,
        private string $integrationName,
        private ?LoggerInterface $logger = null,
        ?DynamicAuthHandler $authHandler = null,
        private ?ConnectionResolverInterface $connectionResolver = null,
        private ?LifecycleEventDispatcher $eventDispatcher = null,
    ) {
        $this->authHandler = $authHandler ?? new DynamicAuthHandler($config, $client, $cache, $integrationName, $logger);
    }

    // ── Single request ─────────────────────────────────────────────────────────

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
        $action = $this->config->getAction($actionName, $body);
        $resolved = $this->resolveForDispatch($action, $connection, $baseUrl);
        $action = $resolved['action'];
        $client = $resolved['client'];

        $this->eventDispatcher?->dispatch(new ActionStarted(
            action: $action,
            integrationName: $this->integrationName,
            timestamp: $startTime,
        ));

        try {
            $auth = $action->getAuthorization();

            if ($auth instanceof DynamicAuthorizationConfig) {
                $response = $this->authHandler->handle(
                    action: $action,
                    auth: $auth,
                    context: $context,
                    headers: $headers,
                    buildResponse: fn (AbstractAction $a, array $respBody, array $respHeaders): ResponseInterface => $this->buildResponse($a, $respBody, $respHeaders),
                    client: $client,
                    cacheDiscriminator: $resolved['cacheDiscriminator'],
                );
            } else {
                $rawResponse = $client->send($action, $context, $headers);
                $response = $this->buildResponse($action, $rawResponse['body'], $rawResponse['headers']);
            }

            $duration = (microtime(true) - $startTime) * 1000;
            $this->eventDispatcher?->dispatch(new ActionCompleted(
                action: $action,
                integrationName: $this->integrationName,
                timestamp: $startTime,
                response: $response,
                durationMs: $duration,
            ));

            return $response;
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $startTime) * 1000;
            $this->eventDispatcher?->dispatch(new ActionFailed(
                action: $action,
                integrationName: $this->integrationName,
                timestamp: $startTime,
                error: $e,
                durationMs: $duration,
            ));
            throw $e;
        }
    }

    // ── Batch requests ─────────────────────────────────────────────────────────

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

        // Scoped to this call and threaded into resolveForDispatch() below,
        // so items sharing one $connection (a common batch pattern — many
        // items for the same tenant) resolve it once instead of once per item.
        $connectionCache = [];

        foreach ($requests as $key => $request) {
            try {
                $action = $this->config->getAction($request->actionName, $request->body);
                $resolved = $this->resolveForDispatch($action, $request->connection, $request->baseUrl, $connectionCache);
                $action = $resolved['action'];
                $client = $resolved['client'];
                $cacheDiscriminator = $resolved['cacheDiscriminator'];

                $auth = $action->getAuthorization();

                if ($auth instanceof DynamicAuthorizationConfig) {
                    $action = $tokenRetry->prepareWithToken(
                        $key,
                        $auth,
                        $cacheDiscriminator,
                        fn (): AbstractAction => $this->authHandler->withStaticToken($action, $auth, client: $client, cacheDiscriminator: $cacheDiscriminator),
                    );
                }

                $prepared[$key] = new PreparedRequest($action, $request->context, $request->headers, $resolved['baseUrl'], $cacheDiscriminator);
            } catch (\Throwable $e) {
                $failures[$key] = $e;
            }
        }

        $raw = $this->dispatchBatch($prepared);
        $raw = $this->retryBatch($raw, $tokenRetry->plan($raw), $prepared);

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
                $results[$key] = BatchResult::success($this->buildResponse($prepared[$key]->action, $rawResult['body'], $rawResult['headers']));
            } catch (\Throwable $e) {
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

    // ── Batch internals ────────────────────────────────────────────────────────

    /**
     * Groups requests by their resolved base URL so that each group can be
     * dispatched through a single client instance — preserving the
     * concurrency BatchClientInterface offers within a group, while still
     * supporting requests that target different base URLs in one batch.
     *
     * @param array<array-key, PreparedRequest> $prepared
     *
     * @return array<array-key, array{body: array<mixed>, headers: array<string, list<string>>}|\Throwable>
     */
    private function dispatchBatch(array $prepared): array
    {
        if ([] === $prepared) {
            return [];
        }

        $groups = [];
        foreach ($prepared as $key => $request) {
            $groups[$request->baseUrl ?? ''][$key] = $request;
        }

        $raw = [];
        foreach ($groups as $baseUrl => $groupPrepared) {
            $client = $this->resolveClient('' === $baseUrl ? null : $baseUrl);
            $raw += $this->dispatchGroup($client, $groupPrepared);
        }

        return $raw;
    }

    /**
     * @param array<array-key, PreparedRequest> $prepared
     *
     * @return array<array-key, array{body: array<mixed>, headers: array<string, list<string>>}|\Throwable>
     */
    private function dispatchGroup(ClientInterface $client, array $prepared): array
    {
        if ($client instanceof BatchClientInterface) {
            return $client->sendMany($prepared);
        }

        $raw = [];

        foreach ($prepared as $key => $request) {
            try {
                $raw[$key] = $client->send($request->action, $request->context, $request->headers);
            } catch (\Throwable $e) {
                $raw[$key] = $e;
            }
        }

        return $raw;
    }

    private function resolveClient(?string $baseUrl): ClientInterface
    {
        return (null !== $baseUrl && $this->client instanceof DynamicBaseUrlClientInterface)
            ? $this->client->withBaseUrl($baseUrl)
            : $this->client;
    }

    /**
     * Resolves the connection (if any) and derives everything both send()
     * and sendMany() need to dispatch a request for it: the action with the
     * connection's authorization override applied, the client targeting
     * its base URL, the base URL itself, and the dynamic-auth cache
     * discriminator. Kept as one method so the two call sites can't drift
     * out of sync with each other on this logic.
     *
     * $connectionCache memoizes resolveConnection() across calls sharing
     * one scalar $connection — pass a variable from sendMany()'s loop so
     * repeated connections across batch items resolve only once; send()
     * doesn't pass one, since there's only ever one call to memoize.
     *
     * @param array<string, ?ConnectionCredentials> $connectionCache
     *
     * @return array{action: AbstractAction, client: ClientInterface, baseUrl: ?string, cacheDiscriminator: ?string}
     */
    private function resolveForDispatch(AbstractAction $action, mixed $connection, ?string $baseUrl, array &$connectionCache = []): array
    {
        $credentials = $this->resolveConnection($connection, $connectionCache);
        $action = $this->applyConnectionAuthorization($action, $credentials);

        $resolvedBaseUrl = $baseUrl ?? $credentials?->baseUrl;
        $cacheDiscriminator = $credentials->connectionId
            ?? (\is_scalar($connection) ? (string) $connection : null)
            ?? $resolvedBaseUrl;

        return [
            'action' => $action,
            'client' => $this->resolveClient($resolvedBaseUrl),
            'baseUrl' => $resolvedBaseUrl,
            'cacheDiscriminator' => $cacheDiscriminator,
        ];
    }

    /**
     * @param array<string, ?ConnectionCredentials> $connectionCache
     *
     * @throws ConnectionResolutionException when $connection is given but
     *                                       no connection_resolver is configured for this integration
     */
    private function resolveConnection(mixed $connection, array &$connectionCache = []): ?ConnectionCredentials
    {
        if (null === $connection) {
            return null;
        }

        $cacheKey = \is_scalar($connection) ? (string) $connection : null;

        if (null !== $cacheKey && \array_key_exists($cacheKey, $connectionCache)) {
            return $connectionCache[$cacheKey];
        }

        if (null === $this->connectionResolver) {
            throw ConnectionResolutionException::noResolverConfigured($this->integrationName);
        }

        $credentials = $this->connectionResolver->resolve($connection);

        if (null !== $cacheKey) {
            $connectionCache[$cacheKey] = $credentials;
        }

        return $credentials;
    }

    /**
     * Rebuilds the action with the resolved connection's AuthorizationConfig
     * when one was resolved — the action instance is otherwise returned
     * unchanged, so integrations that never use runtime connections pay no
     * cost and keep their YAML-configured authorization untouched.
     */
    private function applyConnectionAuthorization(AbstractAction $action, ?ConnectionCredentials $credentials): AbstractAction
    {
        if (null === $credentials?->authorization) {
            return $action;
        }

        return $action::create(
            method: $action->getMethod(),
            path: $action->getRawPath(),
            body: $action->getBody(),
            authorization: $credentials->authorization,
            cacheTtl: $action->getCacheTtl(),
        );
    }

    /**
     * Executes the retry batch produced by BatchTokenRetry::plan(): re-prepares
     * each item with a freshly resolved token and dispatches them together.
     *
     * $prepared is updated in place for retried keys so the caller's copy
     * reflects the fresh-token action actually used — buildResponse() must
     * not see the stale, cache-deleted pre-retry action.
     *
     * @param array<array-key, array{body: array<mixed>, headers: array<string, list<string>>}|\Throwable> $raw
     * @param array<array-key, DynamicAuthorizationConfig>                                                 $toRetry
     * @param array<array-key, PreparedRequest>                                                            $prepared
     *
     * @return array<array-key, array{body: array<mixed>, headers: array<string, list<string>>}|\Throwable>
     */
    private function retryBatch(array $raw, array $toRetry, array &$prepared): array
    {
        if ([] !== $toRetry) {
            $this->logger?->warning('Retrying batch items after 401 with a fresh token', [
                'integration' => $this->integrationName,
                'count' => \count($toRetry),
                'keys' => array_keys($toRetry),
            ]);
        }

        $retryPrepared = [];

        foreach ($toRetry as $key => $auth) {
            try {
                $original = $prepared[$key];
                $client = $this->resolveClient($original->baseUrl);
                $retryPrepared[$key] = new PreparedRequest(
                    $this->authHandler->withStaticToken($original->action, $auth, client: $client, cacheDiscriminator: $original->cacheDiscriminator),
                    $original->context,
                    $original->headers,
                    $original->baseUrl,
                    $original->cacheDiscriminator,
                );
            } catch (\Throwable $e) {
                $raw[$key] = $e;
            }
        }

        foreach ($this->dispatchBatch($retryPrepared) as $key => $result) {
            $raw[$key] = $result;
        }

        foreach ($retryPrepared as $key => $request) {
            $prepared[$key] = $request;
        }

        return $raw;
    }

    // ── Response building ──────────────────────────────────────────────────────

    /**
     * @param array<mixed>                $body
     * @param array<string, list<string>> $headers
     */
    private function buildResponse(AbstractAction $action, array $body, array $headers): ResponseInterface
    {
        if (!$action::hasResponse()) {
            return new EmptyResponse();
        }

        return $this->applyMapper($action, $body, $headers);
    }

    /**
     * @param array<mixed>                $body
     * @param array<string, list<string>> $headers
     */
    private function applyMapper(AbstractAction $action, array $body, array $headers): ResponseInterface
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

        return $mapperClass::map($action, $body, $headers);
    }
}
