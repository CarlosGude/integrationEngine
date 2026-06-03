<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

abstract class AbstractAction
{
    private ?ActionContextInterface $context = null;

    final protected function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly ?ActionBodyInterface $body,
        private readonly ?AuthorizationConfig $authorization,
    ) {}

    final public static function create(
        string $method,
        string $path,
        ?ActionBodyInterface $body = null,
        ?AuthorizationConfig $authorization = null,
    ): static {
        return new static($method, $path, $body, $authorization);
    }

    final public function withContext(?ActionContextInterface $context = null): static
    {
        $clone = clone $this;
        $clone->context = $context;

        return $clone;
    }

    final public function getMethod(): string
    {
        return $this->method;
    }

    final public function getPath(): string
    {
        $resolver = $this->resolvePathCallback();

        if (null !== $resolver) {
            return $resolver($this->path, $this->context);
        }

        return $this->defaultResolvePath($this->path, $this->context);
    }

    final public function getBody(): ?ActionBodyInterface
    {
        return $this->body;
    }

    final public function getAuthorization(): ?AuthorizationConfig
    {
        return $this->authorization;
    }

    abstract public static function getName(): string;

    abstract public static function hasResponse(): bool;

    /**
     * @return null|class-string<AbstractMapper>
     */
    abstract public static function mapper(): ?string;

    protected function resolvePathCallback(): ?callable
    {
        return null;
    }

    final protected function defaultResolvePath(string $path, ActionContextInterface $context): string
    {
        $context = $context?->toArray() ?? [];
        if ([] === $context) {
            return $path;
        }

        return preg_replace_callback(
            '/\{(\w+)\}/',
            static function (array $matches) use ($context, $path) {
                $key = $matches[1];

                if (!\array_key_exists($key, $context)) {
                    throw new \RuntimeException(
                        \sprintf(
                            'Missing path parameter "%s" for path "%s"',
                            $key,
                            $path
                        )
                    );
                }

                return (string) $context[$key];
            },
            $path
        );
    }
}
