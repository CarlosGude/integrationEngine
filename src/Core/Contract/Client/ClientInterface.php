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
     * the action's mapper as separate arguments.
     *
     * @return array{body: array<mixed>, headers: array<string, list<string>>}
     */
    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array;
}
