<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

interface ClientInterface
{
    /**
     * Executes the HTTP request and returns the raw response payload.
     * The engine will pass this array to the action's mapper.
     */
    public function send(AbstractAction $action): array;
}
