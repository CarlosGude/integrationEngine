<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Action;

interface PathResolvableContextInterface extends ActionContextInterface
{
    /**
     * Controls how this context resolves the path.
     * Receives the raw path from YAML (e.g. "/character").
     * Return null to fall back to the default {placeholder} resolver.
     */
    public function resolvePath(string $path): ?string;
}
