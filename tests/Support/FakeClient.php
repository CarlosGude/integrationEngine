<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Support;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\ClientInterface;

final class FakeClient implements ClientInterface
{
    /**
     * @var array<string, array>
     */
    private array $responses = [];

    public function setResponse(string $actionName, array $response): void
    {
        $this->responses[$actionName] = $response;
    }

    public function send(AbstractAction $action): array
    {
        $name = $action::getName();

        if (!isset($this->responses[$name])) {
            return [];
        }

        return $this->responses[$name];
    }
}