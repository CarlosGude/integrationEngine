<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

abstract class AbstractAction
{
    abstract public static function create(
        string $method,
        string $path,
        ?ActionBodyInterface $body,
        mixed $authorization
    ): static;

    abstract public function getMethod(): string;
    abstract public function getPath(): string;
    abstract public function getBody(): ?ActionBodyInterface;
    abstract public function getAuthorization(): mixed;

    /**
     * Body exists (POST/PUT/PATCH usually)
     */
    abstract public static function hasBody(): bool;

    /**
     * Response exists (some actions like DELETE may not return structured response)
     */
    abstract public static function hasResponse(): bool;

    /**
     * Mapper used only if hasResponse() === true
     *
     * @return class-string<AbstractMapper>|null
     */
    abstract public static function mapper(): ?string;

    /**
     * NEW: declare response type explicitly (optional but powerful)
     *
     * @return class-string<ResponseInterface>|null
     */
    public static function response(): ?string
    {
        return null;
    }
}