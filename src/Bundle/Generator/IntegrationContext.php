<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator;

final readonly class IntegrationContext
{
    public function __construct(
        public string $name,
        public string $action,
        public string $baseNamespace,
        public string $basePath,
    ) {
    }

    public function integrationNamespace(): string
    {
        return $this->baseNamespace . '\\' . $this->name;
    }

    public function requestNamespace(): string
    {
        return $this->integrationNamespace() . '\\Request';
    }

    public function responseNamespace(): string
    {
        return $this->integrationNamespace() . '\\Response';
    }
}