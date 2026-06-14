<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Client\BatchClientInterface;
use IntegrationEngine\Core\Contract\Client\ClientInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;

/**
 * A batch-capable client for engine tests: delegates each request to an
 * inner FakeClient (same responses/exceptions API) and records every
 * sendMany() call so tests can assert the engine routed through the batch
 * interface instead of looping over send().
 */
final class FakeBatchClient implements BatchClientInterface, ClientInterface
{
    /** @var list<array<array-key, PreparedRequest>> */
    private array $batches = [];

    public function __construct(
        private readonly FakeClient $inner = new FakeClient(),
    ) {}

    public function inner(): FakeClient
    {
        return $this->inner;
    }

    public function batchCount(): int
    {
        return \count($this->batches);
    }

    /** @return list<array<array-key, PreparedRequest>> */
    public function batches(): array
    {
        return $this->batches;
    }

    /** @return array<mixed> */
    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
        return $this->inner->send($action, $context, $headers);
    }

    public function sendMany(array $requests): array
    {
        $this->batches[] = $requests;

        $results = [];

        foreach ($requests as $key => $request) {
            try {
                $results[$key] = $this->inner->send($request->action, $request->context, $request->headers);
            } catch (\Throwable $e) {
                $results[$key] = $e;
            }
        }

        return $results;
    }
}
