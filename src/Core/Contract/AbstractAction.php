<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

abstract class AbstractAction
{
    private array $context = [];

    final protected function __construct(
        private string $method,
        private string $path,
        private ?ActionBodyInterface $body,
        private ?AuthorizationConfig $authorization,
    ) {}

    final public static function create(
        string $method,
        string $path,
        ?ActionBodyInterface $body = null,
        ?AuthorizationConfig $authorization = null,
    ): static {
        return new static($method, $path, $body, $authorization);
    }

    final public function withContext(array $context): static
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

    final public function getAuthorization(): mixed
    {
        return $this->authorization;
    }

    abstract public static function getName(): string;

    abstract public static function hasBody(): bool;

    abstract public static function hasResponse(): bool;

    /**
     * @return null|class-string<AbstractMapper>
     */
    abstract public static function mapper(): ?string;

    protected function resolvePathCallback(): ?callable
    {
        return null;
    }

    final protected function defaultResolvePath(string $path, array $context): string
    {
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
