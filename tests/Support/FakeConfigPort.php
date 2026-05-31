<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Support;

use IntegrationEngine\Core\Port\ConfigPort;
use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\ActionBodyInterface;

final class FakeConfigPort implements ConfigPort
{
    /**
     * @var array<string, AbstractAction>
     */
    private array $actions = [];

    public function setAction(string $name, AbstractAction $action): void
    {
        $this->actions[$name] = $action;
    }

    public function getAction(string $name, ?ActionBodyInterface $body = null): AbstractAction
    {
        if (!isset($this->actions[$name])) {
            throw new \RuntimeException("Action [$name] not found in FakeConfigPort.");
        }

        return $this->actions[$name];
    }
}