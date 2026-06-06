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
        public string $clientType = 'rest',
        public bool $adapterRequiresPath = true,
        public bool $adapterRequiresMethod = true,
    ) {}

    public function integrationNamespace(): string
    {
        return $this->baseNamespace.'\\'.$this->name;
    }

    public function actionNamespace(): string
    {
        return $this->integrationNamespace().'\\'.$this->action;
    }

    public function requestNamespace(): string
    {
        return $this->actionNamespace().'\Request';
    }

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

    public function isPatch(): bool
    {
        return 'PATCH' === strtoupper($this->method);
    }

    public function hasBody(): bool
    {
        if (!$this->adapterRequiresMethod) {
            // Adapters that don't use HTTP methods (GraphQL, SOAP) always have a body
            return true;
        }

        return $this->isPost() || $this->isPut() || $this->isPatch();
    }

    public function hasResponse(): bool
    {
        if (!$this->adapterRequiresMethod) {
            // Adapters that don't use HTTP methods always have a response
            return true;
        }

        return !$this->isDelete();
    }

    public function needsGraphQLBodyHint(): bool
    {
        // Show GraphQLBodyInterface hint when adapter doesn't use path/method
        return !$this->adapterRequiresPath && !$this->adapterRequiresMethod;
    }

    public function with(
        ?string $action = null,
        ?string $method = null,
        ?string $path = null,
    ): self {
        return new self(
            name: $this->name,
            action: $action ?? $this->action,
            method: $method ?? $this->method,
            path: $path ?? $this->path,
            baseNamespace: $this->baseNamespace,
            basePath: $this->basePath,
            clientType: $this->clientType,
            adapterRequiresPath: $this->adapterRequiresPath,
            adapterRequiresMethod: $this->adapterRequiresMethod,
        );
    }
}
