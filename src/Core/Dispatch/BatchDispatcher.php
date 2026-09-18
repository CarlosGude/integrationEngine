<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Dispatch;

use IntegrationEngine\Core\Batch\BatchTokenRetry;
use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\Auth\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Contract\Client\BatchClientInterface;
use IntegrationEngine\Core\Contract\Client\ClientInterface;
use IntegrationEngine\Core\Contract\Client\DynamicBaseUrlClientInterface;
use Psr\Log\LoggerInterface;

final class BatchDispatcher
{
    public function __construct(
        private ClientInterface $client,
        private string $integrationName,
        private ?LoggerInterface $logger = null,
    ) {}

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
    public function dispatch(array $prepared): array
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
     * Executes the retry batch produced by BatchTokenRetry::plan(): re-prepares
     * each item with a freshly resolved token and dispatches them together.
     *
     * $prepared is updated in place for retried keys so the caller's copy
     * reflects the fresh-token action actually used.
     *
     * @param array<array-key, array{body: array<mixed>, headers: array<string, list<string>>}|\Throwable> $raw
     * @param array<array-key, DynamicAuthorizationConfig>                                                 $toRetry
     * @param array<array-key, PreparedRequest>                                                            $prepared
     *
     * @return array<array-key, array{body: array<mixed>, headers: array<string, list<string>>}|\Throwable>
     */
    public function retry(
        array $raw,
        array $toRetry,
        callable $prepareRetry,
        array &$prepared,
    ): array {
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
                $retryPrepared[$key] = $prepareRetry($key, $original, $auth);
            } catch (\Throwable $e) {
                $raw[$key] = $e;
            }
        }

        foreach ($this->dispatch($retryPrepared) as $key => $result) {
            $raw[$key] = $result;
        }

        foreach ($retryPrepared as $key => $request) {
            $prepared[$key] = $request;
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
}
