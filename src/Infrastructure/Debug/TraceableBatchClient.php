<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Debug;

use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Client\BatchClientInterface;
use IntegrationEngine\Core\Contract\Client\ClientInterface;
use IntegrationEngine\Core\Contract\Client\DynamicBaseUrlClientInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use IntegrationEngine\Core\Exception\RequestResponseException;

/**
 * Same as TraceableClient, plus BatchClientInterface — used instead of
 * TraceableClient when the decorated client supports concurrent batches,
 * so IntegrationEngine::dispatchBatch()'s instanceof check still finds it.
 *
 * Composes a TraceableClient for send() rather than extending it: the two
 * classes have incompatible constructors (this one requires a batch-capable
 * client), so sharing behavior via inheritance would make withBaseUrl()'s
 * `static` return type unsafe to implement in the base class.
 */
final class TraceableBatchClient implements BatchClientInterface, ClientInterface, DynamicBaseUrlClientInterface
{
    private readonly TraceableClient $delegate;

    public function __construct(
        private readonly BatchClientInterface&ClientInterface $batchClient,
        private readonly string $integrationName,
        private readonly IntegrationEngineDataCollector $collector,
    ) {
        $this->delegate = new TraceableClient($batchClient, $integrationName, $collector);
    }

    public function withBaseUrl(string $baseUrl): static
    {
        if (!$this->batchClient instanceof DynamicBaseUrlClientInterface) {
            return $this;
        }

        return new self($this->batchClient->withBaseUrl($baseUrl), $this->integrationName, $this->collector);
    }

    /** @return array<mixed> */
    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
        return $this->delegate->send($action, $context, $headers);
    }

    /**
     * @param array<array-key, PreparedRequest> $requests
     *
     * @return array<array-key, array<mixed>|\Throwable>
     */
    public function sendMany(array $requests): array
    {
        $start = microtime(true);
        $results = $this->batchClient->sendMany($requests);
        $durationMs = (microtime(true) - $start) * 1000;

        foreach ($requests as $key => $request) {
            $result = $results[$key] ?? null;
            $error = $result instanceof \Throwable ? $result : null;

            $this->collector->recordCall(
                integrationName: $this->integrationName,
                actionName: $request->action::getName(),
                method: $request->action->getMethod(),
                path: $request->action->getRawPath(),
                durationMs: $durationMs,
                error: $error,
                statusCode: $error instanceof RequestResponseException ? $error->statusCode : null,
            );
        }

        return $results;
    }
}
