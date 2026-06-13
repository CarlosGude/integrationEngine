<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Batch;

use IntegrationEngine\Core\Contract\ResponseInterface;
use IntegrationEngine\Core\Exception\BatchMapperActionMismatchException;

/**
 * Second-stage mapper for homogeneous batches (same action, N contexts).
 * consolidate() receives already-mapped DTOs, not raw arrays — the
 * individual mapper ran first, item by item, as in any send() call.
 *
 * Usage: $engine->sendMany($requests)->mapWith(MyBatchMapper::class)
 */
abstract class AbstractBatchMapper
{
    /**
     * Returns the fully qualified action class all items in the batch must share.
     *
     * Example:
     *   public static function getAction(): string { return GetAccommodationAction::class; }
     */
    abstract public static function getAction(): string;

    /**
     * Validates the action invariant, then delegates to consolidate().
     * Called by BatchResultCollection::mapWith() — do not call directly.
     *
     * @throws BatchMapperActionMismatchException if any resolved item belongs to a different action
     */
    final public static function map(BatchResultCollection $collection): ResponseInterface
    {
        foreach ($collection->keys() as $key) {
            $actionClass = $collection->actionClassFor($key);

            if (null !== $actionClass && $actionClass !== static::getAction()) {
                throw new BatchMapperActionMismatchException(
                    mapperClass: static::class,
                    expectedActionClass: static::getAction(),
                    key: (string) $key,
                    actualActionClass: $actionClass,
                );
            }
        }

        return static::consolidate($collection);
    }

    /**
     * Consolidates the N mapped responses into one.
     * Receives the full BatchResultCollection — decide here whether to fail on
     * partial failures or work with $results->responses() (successes only).
     */
    abstract protected static function consolidate(BatchResultCollection $results): ResponseInterface;
}
