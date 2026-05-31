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

    abstract public static function hasBody(): bool;
    abstract public static function hasResponse(): bool;

    /**
     * @return class-string<AbstractMapper>|null
     */
    abstract public static function mapper(): ?string;
}