<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Client;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;

interface ClientInterface
{
    /**
     * Executes the HTTP request and returns the raw response payload.
     * The engine will pass this array to the action's mapper.
     *
     * @return array<mixed>
     */
    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array;
}
