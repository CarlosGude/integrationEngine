<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Action;

use IntegrationEngine\Core\Contract\Auth\AuthorizationConfig;
use IntegrationEngine\Core\Contract\Mapper\AbstractMapper;
use IntegrationEngine\Core\Exception\PathResolutionException;

abstract class AbstractAction
{
    final protected function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly ?ActionBodyInterface $body,
        private readonly ?AuthorizationConfig $authorization,
        private readonly ?int $cacheTtl = null,
    ) {}

    final public static function create(
        string $method,
        string $path,
        ?ActionBodyInterface $body = null,
        ?AuthorizationConfig $authorization = null,
        ?int $cacheTtl = null,
    ): static {
        return new static($method, $path, $body, $authorization, $cacheTtl);
    }

    final public function getMethod(): string
    {
        return $this->method;
    }

    final public function getPath(?ActionContextInterface $context = null): string
    {
        if ($context instanceof PathResolvableContextInterface) {
            $resolved = $context->resolvePath($this->path);
            if (null !== $resolved) {
                if ('' === $resolved) {
                    throw PathResolutionException::resolverReturnedEmptyPath();
                }

                return $resolved;
            }
        }

        return $this->defaultResolvePath($this->path, $context);
    }

    final public function getRawPath(): string
    {
        return $this->path;
    }

    final public function getBody(): ?ActionBodyInterface
    {
        return $this->body;
    }

    final public function getAuthorization(): ?AuthorizationConfig
    {
        return $this->authorization;
    }

    final public function getCacheTtl(): ?int
    {
        return $this->cacheTtl;
    }

    abstract public static function getName(): string;

    abstract public static function hasResponse(): bool;

    /**
     * @return null|class-string<AbstractMapper>
     */
    abstract public static function mapper(): ?string;

    final protected function defaultResolvePath(string $path, ?ActionContextInterface $context): string
    {
        $contextData = $context?->toArray() ?? [];

        return preg_replace_callback(
            '/\{(\w+)}/',
            static function (array $matches) use ($contextData, $path) {
                $key = $matches[1];

                if (!\array_key_exists($key, $contextData)) {
                    throw PathResolutionException::missingParameter($key, $path);
                }

                $value = $contextData[$key];
                if (!\is_scalar($value)) {
                    throw PathResolutionException::nonScalarParameter($key);
                }

                return (string) $value;
            },
            $path
        ) ?? throw PathResolutionException::pcreError($path);
    }
}
