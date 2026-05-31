<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

abstract readonly class AbstractAction
{
    final protected function __construct(
        private string $method,
        private string $path,
        private ?ActionBodyInterface $body,
        private mixed $authorization,
    ) {
    }

    final public static function create(
        string $method,
        string $path,
        ?ActionBodyInterface $body,
        mixed $authorization,
    ): static {
        return new static($method, $path, $body, $authorization);
    }

    final public function getMethod(): string
    {
        return $this->method;
    }

    final public function getPath(): string
    {
        return $this->path;
    }

    final public function getBody(): ?ActionBodyInterface
    {
        return $this->body;
    }

    final public function getAuthorization(): mixed
    {
        return $this->authorization;
    }

    abstract public static function getName(): string;

    /** Body exists (POST/PUT) */
    abstract public static function hasBody(): bool;

    /** Response exists (DELETE returns null) */
    abstract public static function hasResponse(): bool;

    /**
     * Mapper used only if hasResponse() === true.
     *
     * @return class-string<AbstractMapper>|null
     */
    abstract public static function mapper(): ?string;
}
