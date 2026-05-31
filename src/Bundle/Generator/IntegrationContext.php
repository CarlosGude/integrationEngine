<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator;

final readonly class IntegrationContext
{
    public function __construct(
        public string $name,
        public string $action,
        public string $method,
        public string $path,
        public string $baseNamespace,
        public string $basePath,
    ) {
    }

    /**
     * Base namespace of the integration module
     * App\Infrastructure\Integrations\Iberia.
     */
    public function integrationNamespace(): string
    {
        return $this->baseNamespace.'\\'.$this->name;
    }

    /**
     * Namespace of the action level.
     */
    public function actionNamespace(): string
    {
        return $this->integrationNamespace().'\\'.$this->action;
    }

    /**
     * Request namespace.
     */
    public function requestNamespace(): string
    {
        return $this->actionNamespace().'\Request';
    }

    /**
     * Response namespace.
     */
    public function responseNamespace(): string
    {
        return $this->actionNamespace().'\Response';
    }

    public function isGet(): bool
    {
        return 'GET' === strtoupper($this->method);
    }

    public function isPost(): bool
    {
        return 'POST' === strtoupper($this->method);
    }

    public function isPut(): bool
    {
        return 'PUT' === strtoupper($this->method);
    }

    public function isDelete(): bool
    {
        return 'DELETE' === strtoupper($this->method);
    }

    public function hasBody(): bool
    {
        return $this->isPost() || $this->isPut();
    }

    public function hasResponse(): bool
    {
        return !$this->isDelete();
    }
}
