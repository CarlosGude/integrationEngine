<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator;

/**
 * Context for webhook code generation.
 *
 * @author Carlos Gude
 */
final readonly class WebhookContext
{
    public function __construct(
        public string $integration,
        public string $event,
        public string $verifierType,
        public string $headerName,
        public string $baseNamespace,
        public string $basePath,
    ) {}

    /**
     * Get the webhook event class name (e.g., ChargeSucceededEvent).
     */
    public function eventClassName(): string
    {
        return str_replace(' ', '', ucwords(str_replace(['.', '_', '-'], ' ', $this->event))).'Event';
    }

    /**
     * Get the webhook parser class name (e.g., ChargeSucceededRequestParser).
     */
    public function parserClassName(): string
    {
        return str_replace(' ', '', ucwords(str_replace(['.', '_', '-'], ' ', $this->event))).'RequestParser';
    }

    public function mapperClassName(): string
    {
        return $this->eventClassName().'Mapper';
    }

    /**
     * Namespace of the generated classes. Mirrors generationPath() so the
     * files are PSR-4 autoloadable.
     */
    public function namespace(): string
    {
        return $this->baseNamespace.'\\'.ucfirst($this->integration);
    }

    /**
     * Get the fully qualified event class name.
     */
    public function eventClassFqn(): string
    {
        return $this->namespace().'\\'.$this->eventClassName();
    }

    /**
     * Get the fully qualified parser class name.
     */
    public function parserClassFqn(): string
    {
        return $this->namespace().'\\'.$this->parserClassName();
    }

    /**
     * Get the directory where files will be generated.
     */
    public function generationPath(): string
    {
        return $this->basePath.'/'.ucfirst($this->integration);
    }
}
