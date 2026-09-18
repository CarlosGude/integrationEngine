<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Client;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;

interface ClientInterface
{
    /**
     * Executes the HTTP request and returns the raw response payload plus
     * the response's HTTP headers. The engine passes body and headers to
     * the action's mapper as separate arguments. statusCode is optional:
     * it only feeds HttpResponseReceived, which reports 0 when it's absent.
     *
     * @return array{body: array<mixed>, headers: array<string, list<string>>, statusCode?: int}
     */
    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array;
}
