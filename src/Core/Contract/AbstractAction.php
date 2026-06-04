<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

abstract class AbstractAction
{
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

    final public function getMethod(): string
    {
        return $this->method;
    }

    final public function getPath(?ActionContextInterface $context = null): string
    {
        $resolver = $this->resolvePathCallback();

        if (null !== $resolver) {
            $result = $resolver($this->path, $context);
            if (!\is_string($result)) {
                throw new \RuntimeException('Path resolver must return a string.');
            }

            return $result;
        }

        return $this->defaultResolvePath($this->path, $context);
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

    final protected function defaultResolvePath(string $path, ?ActionContextInterface $context): string
    {
        $contextData = $context?->toArray() ?? [];

        return preg_replace_callback(
            '/\{(\w+)\}/',
            static function (array $matches) use ($contextData, $path) {
                $key = $matches[1];

                if (!\array_key_exists($key, $contextData)) {
                    throw new \RuntimeException(
                        \sprintf(
                            'Missing path parameter "%s" for path "%s"',
                            $key,
                            $path
                        )
                    );
                }

                $value = $contextData[$key];
                if (!\is_scalar($value)) {
                    throw new \RuntimeException(
                        \sprintf('Path parameter "%s" must be a scalar value.', $key)
                    );
                }

                return (string) $value;
            },
            $path
        ) ?? $path;
    }
}
