<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionBodyInterface;
use IntegrationEngine\Core\Exception\ActionNotFoundException;
use IntegrationEngine\Core\Port\ConfigPort;

final class FakeConfigPort implements ConfigPort
{
    /** @var array<string, AbstractAction> */
    private array $actions = [];

    public function register(string $name, AbstractAction $action): void
    {
        $this->actions[$name] = $action;
    }

    public function getAction(string $name, ?ActionBodyInterface $bodyData = null): AbstractAction
    {
        if (!isset($this->actions[$name])) {
            throw new ActionNotFoundException($name);
        }

        return $this->actions[$name];
    }
}
