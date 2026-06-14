<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Batch;

use IntegrationEngine\Core\Contract\Response\ResponseInterface;
use IntegrationEngine\Core\Exception\BatchMapperActionMismatchException;

/**
 * @implements \IteratorAggregate<array-key, BatchResult>
 * @implements \ArrayAccess<array-key, BatchResult>
 */
final readonly class BatchResultCollection implements \IteratorAggregate, \Countable, \ArrayAccess
{
    /**
     * @param array<array-key, BatchResult> $results
     * @param array<array-key, string>      $actionClasses action class per item, keyed like $results
     */
    public function __construct(
        private array $results,
        private array $actionClasses = [],
    ) {}

    /** @return \ArrayIterator<array-key, BatchResult> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->results);
    }

    public function count(): int
    {
        return \count($this->results);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->results[$offset]);
    }

    public function offsetGet(mixed $offset): BatchResult
    {
        if (!isset($this->results[$offset])) {
            throw new \OutOfBoundsException(\sprintf('No batch result for key "%s".', $offset));
        }

        return $this->results[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new \LogicException('BatchResultCollection is read-only.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new \LogicException('BatchResultCollection is read-only.');
    }

    /** @return list<array-key> */
    public function keys(): array
    {
        return array_keys($this->results);
    }

    public function hasFailures(): bool
    {
        foreach ($this->results as $result) {
            if (!$result->isSuccess()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns only the successful responses, keyed like the original input.
     *
     * @return array<array-key, ResponseInterface>
     */
    public function responses(): array
    {
        $responses = [];

        foreach ($this->results as $key => $result) {
            if ($result->isSuccess()) {
                $responses[$key] = $result->response();
            }
        }

        return $responses;
    }

    /**
     * Returns only the failures, keyed like the original input.
     *
     * @return array<array-key, \Throwable>
     */
    public function errors(): array
    {
        $errors = [];

        foreach ($this->results as $key => $result) {
            $error = $result->error();
            if (null !== $error) {
                $errors[$key] = $error;
            }
        }

        return $errors;
    }

    /**
     * Returns the action class recorded for a given key, or null when the item
     * failed during preparation (before an action could be resolved).
     */
    public function actionClassFor(int|string $key): ?string
    {
        return $this->actionClasses[$key] ?? null;
    }

    /**
     * Consolidates the collection through a batch mapper into one response.
     *
     * The mapper class must extend AbstractBatchMapper. The mapper's getAction()
     * is checked against every item's recorded action class — mismatches throw
     * BatchMapperActionMismatchException. Items that failed during preparation
     * (null actionClass) are passed through to consolidate() as-is; the mapper
     * decides what to do with them via $results->hasFailures() / $results->errors().
     *
     * @throws \InvalidArgumentException          if $batchMapperClass is not an AbstractBatchMapper
     * @throws BatchMapperActionMismatchException if any item's action mismatches
     */
    public function mapWith(string $batchMapperClass): ResponseInterface
    {
        if (!is_a($batchMapperClass, AbstractBatchMapper::class, true)) {
            throw new \InvalidArgumentException(\sprintf(
                '"%s" must extend %s.',
                $batchMapperClass,
                AbstractBatchMapper::class,
            ));
        }

        return $batchMapperClass::map($this);
    }
}
