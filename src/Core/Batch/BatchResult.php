<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Batch;

use IntegrationEngine\Core\Contract\Response\ResponseInterface;

/**
 * The outcome of one request inside a batch: either the mapped response
 * or the failure that request produced. sendMany() never throws for
 * individual requests — inspect each result instead.
 */
final readonly class BatchResult
{
    private function __construct(
        private ResponseInterface|\Throwable $value,
    ) {}

    public static function success(ResponseInterface $response): self
    {
        return new self($response);
    }

    public static function failure(\Throwable $error): self
    {
        return new self($error);
    }

    public function isSuccess(): bool
    {
        return $this->value instanceof ResponseInterface;
    }

    /**
     * Returns the mapped response, or rethrows the stored failure.
     *
     * @throws \Throwable
     */
    public function response(): ResponseInterface
    {
        if ($this->value instanceof \Throwable) {
            throw $this->value;
        }

        return $this->value;
    }

    public function error(): ?\Throwable
    {
        return $this->value instanceof \Throwable ? $this->value : null;
    }
}
