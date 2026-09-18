<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Client;

use IntegrationEngine\Core\Batch\PreparedRequest;

/**
 * Optional capability for clients that can execute several requests
 * concurrently. When the integration's client implements it, the engine
 * routes sendMany() batches through it; otherwise the engine falls back
 * to sequential ClientInterface::send() calls per request.
 */
interface BatchClientInterface
{
    /**
     * Executes all requests, concurrently where the transport allows it.
     *
     * Must return one entry per input key, preserving keys: the
     * {body, headers} payload on success (the same shape ClientInterface::send()
     * returns), or the Throwable that the equivalent send() call would have
     * thrown. One failed request must never abort the rest of the batch.
     *
     * @param array<array-key, PreparedRequest> $requests
     *
     * @return array<array-key, array{body: array<mixed>, headers: array<string, list<string>>, statusCode?: int}|\Throwable>
     */
    public function sendMany(array $requests): array;
}
