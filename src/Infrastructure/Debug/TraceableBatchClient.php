<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Debug;

use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\Client\BatchClientInterface;
use IntegrationEngine\Core\Contract\Client\ClientInterface;
use IntegrationEngine\Core\Exception\RequestResponseException;

/**
 * Same as TraceableClient, plus BatchClientInterface — used instead of
 * TraceableClient when the decorated client supports concurrent batches,
 * so IntegrationEngine::dispatchBatch()'s instanceof check still finds it.
 */
final class TraceableBatchClient extends TraceableClient implements BatchClientInterface
{
    public function __construct(
        private readonly BatchClientInterface&ClientInterface $batchClient,
        string $integrationName,
        IntegrationEngineDataCollector $collector,
    ) {
        parent::__construct($batchClient, $integrationName, $collector);
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
