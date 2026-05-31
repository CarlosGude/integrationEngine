<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Registry;

use IntegrationEngine\Core\Exception\IntegrationNotFoundException;
use IntegrationEngine\Core\Integration;

final class IntegrationRegistry
{
    /** @var array<string, Integration> */
    private array $integrations = [];

    public function register(string $name, Integration $integration): void
    {
        $this->integrations[$name] = $integration;
    }

    /**
     * Get an integration by its registered name constant.
     *
     * Usage: $registry->get(AcmeErpIntegration::NAME)
     *
     * @throws IntegrationNotFoundException
     */
    public function get(string $name): Integration
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
