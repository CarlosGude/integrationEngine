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
use IntegrationEngine\Core\Contract\Response\ResponseInterface;
use IntegrationEngine\Core\Exception\MapperActionMismatchException;
use IntegrationEngine\Core\Exception\NotMappedActionException;
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
    ) {
        $this->authHandler = $authHandler ?? new DynamicAuthHandler($config, $client, $cache, $integrationName, $logger);
    }

    // ── Single request ─────────────────────────────────────────────────────────

    public function send(
        string $actionName,
        ?ActionContextInterface $context = null,
        ?ActionBodyInterface $body = null,
        ?RequestHeadersInterface $headers = null,
        ?string $baseUrl = null,
    ): ResponseInterface {
        $action = $this->config->getAction($actionName, $body);
        $auth = $action->getAuthorization();
        $client = $this->resolveClient($baseUrl);

        if ($auth instanceof DynamicAuthorizationConfig) {
            return $this->authHandler->handle(
                action: $action,
                auth: $auth,
                context: $context,
                headers: $headers,
                buildResponse: fn (AbstractAction $a, array $r): ResponseInterface => $this->buildResponse($a, $r),
                client: $client,
            );
        }

        $rawResponse = $client->send($action, $context, $headers);

        return $this->buildResponse($action, $rawResponse);
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

        foreach ($requests as $key => $request) {
            try {
                $action = $this->config->getAction($request->actionName, $request->body);
                $auth = $action->getAuthorization();
                $client = $this->resolveClient($request->baseUrl);

                if ($auth instanceof DynamicAuthorizationConfig) {
                    $action = $tokenRetry->prepareWithToken(
                        $key,
                        $auth,
                        fn (): AbstractAction => $this->authHandler->withStaticToken($action, $auth, client: $client),
                    );
                }

                $prepared[$key] = new PreparedRequest($action, $request->context, $request->headers, $request->baseUrl);
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
                $results[$key] = BatchResult::success($this->buildResponse($prepared[$key]->action, $rawResult));
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
     * @return array<array-key, array<mixed>|\Throwable>
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
     * @return array<array-key, array<mixed>|\Throwable>
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
     * Executes the retry batch produced by BatchTokenRetry::plan(): re-prepares
     * each item with a freshly resolved token and dispatches them together.
     *
     * @param array<array-key, array<mixed>|\Throwable>    $raw
     * @param array<array-key, DynamicAuthorizationConfig> $toRetry
     * @param array<array-key, PreparedRequest>            $originalPrepared
     *
     * @return array<array-key, array<mixed>|\Throwable>
     */
    private function retryBatch(array $raw, array $toRetry, array $originalPrepared): array
    {
        if ([] !== $toRetry) {
            $this->logger?->warning('Retrying batch items after 401 with a fresh token', [
                'integration' => $this->integrationName,
                'count' => \count($toRetry),
                'keys' => array_keys($toRetry),
            ]);
        }

        $prepared = [];

        foreach ($toRetry as $key => $auth) {
            try {
                $original = $originalPrepared[$key];
                $client = $this->resolveClient($original->baseUrl);
                $prepared[$key] = new PreparedRequest(
                    $this->authHandler->withStaticToken($original->action, $auth, client: $client),
                    $original->context,
                    $original->headers,
                    $original->baseUrl,
                );
            } catch (\Throwable $e) {
                $raw[$key] = $e;
            }
        }

        foreach ($this->dispatchBatch($prepared) as $key => $result) {
            $raw[$key] = $result;
        }

        return $raw;
    }

    // ── Response building ──────────────────────────────────────────────────────

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
