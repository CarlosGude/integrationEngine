<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

interface ActionContextInterface
{
    /** @param array<string, mixed> $data */
    public static function create(array $data): self;

    /** @return array<string, mixed> */
    public function toArray(): array;

    /**
     * Override to control how this context resolves the path.
     * Receives the raw path from YAML (e.g. "/character").
     * Return null to fall back to the default {placeholder} resolver.
     */
    public function resolvePath(string $path): ?string;
}
