<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator;

final readonly class IntegrationContext
{
    public function __construct(
        public string $name,
        public string $action,
        public string $namespace,
        public string $basePath,
    ) {
    }

    public function integrationNamespace(): string
    {
        return $this->namespace . '\\' . $this->name;
    }

    public function integrationPath(): string
    {
        return $this->basePath . '/' . $this->name;
    }
}