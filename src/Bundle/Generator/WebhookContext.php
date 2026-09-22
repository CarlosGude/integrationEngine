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
    ) {
        if (1 !== preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $integration) || 1 !== preg_match('/^[a-zA-Z0-9_]+(?:[.\/-][a-zA-Z0-9_]+)*$/D', $event)) {
            throw new \InvalidArgumentException('Integration and event must be valid identifiers.');
        }
    }

    /**
     * Get the webhook event class name (e.g., ChargeSucceededEvent).
     */
    public function eventClassName(): string
    {
        return $this->studlyEvent().'Event';
    }

    /**
     * Get the webhook parser class name (e.g., ChargeSucceededRequestParser).
     */
    public function parserClassName(): string
    {
        return $this->studlyEvent().'RequestParser';
    }

    public function mapperClassName(): string
    {
        return $this->eventClassName().'Mapper';
    }

    /**
     * Get the webhook consumer class name (e.g., ChargeSucceededConsumer).
     */
    public function consumerClassName(): string
    {
        return $this->studlyEvent().'Consumer';
    }

    /**
     * The key that ties the three ends together: the URL segment
     * (POST /webhook/<key>), the framework.webhook.routing entry, and the
     * consumer's AsRemoteEventConsumer name. One key per event type, so a
     * provider can deliver several of them side by side.
     */
    public function routingKey(): string
    {
        return strtolower($this->integration.'_'.str_replace(['.', '-', '/'], '_', $this->event));
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

    /**
     * The event name as a class-name fragment: every separator a provider
     * uses — dots, slashes, dashes — collapses into StudlyCase.
     */
    private function studlyEvent(): string
    {
        return str_replace(' ', '', ucwords(str_replace(['.', '_', '-', '/'], ' ', $this->event)));
    }
}
