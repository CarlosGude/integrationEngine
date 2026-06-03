<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Registry;

use IntegrationEngine\Core\Exception\IntegrationNotFoundException;
use IntegrationEngine\Core\IntegrationEngine;

final class IntegrationRegistry
{
    /** @var array<string, IntegrationEngine> */
    private array $integrations = [];

    public function register(string $name, IntegrationEngine $integration): void
    {
        if ($name === '__MUST_OVERRIDE__' || $name === '') {
            throw new \Exception('The name of the integration must be declarative');
        }
        $this->integrations[$name] = $integration;
    }

    /**
     * Get an integration by its registered name constant.
     *
     * Usage: $registry->get(AcmeErpIntegration::NAME)
     *
     * @throws IntegrationNotFoundException
     */
    public function get(string $name): IntegrationEngine
    {
        if (!isset($this->integrations[$name])) {
            throw new IntegrationNotFoundException($name);
        }

        return $this->integrations[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->integrations[$name]);
    }
}
