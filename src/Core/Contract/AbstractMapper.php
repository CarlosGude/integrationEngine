<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

use IntegrationEngine\Core\Exception\MapperActionMismatchException;

abstract class AbstractMapper
{
    /**
     * Returns the fully qualified action class this mapper belongs to.
     *
     * Example:
     *   public static function getAction(): string { return GetOrdersAction::class; }
     */
    abstract public static function getAction(): string;

    /**
     * Transforms the raw response array into a typed ResponseInterface.
     * Implement this in your mapper — the action type is guaranteed to match getAction().
     *
     * Example:
     *   protected static function transform(AbstractAction $action, array $response): ResponseInterface
     *   {
     *       return new GetOrdersResponse(
     *           orders: $response['data'],
     *           total:  $response['meta']['total'],
     *       );
     *   }
     */
    abstract protected static function transform(
        AbstractAction $action,
        array $response,
    ): ResponseInterface;

    /**
     * Validates the action and delegates to transform().
     * Called automatically by the engine — do not override.
     *
     * @throws MapperActionMismatchException
     */
    final public static function map(
        AbstractAction $action,
        array $response,
    ): ResponseInterface {
        if ($action::class !== static::getAction()) {
            throw new MapperActionMismatchException(mapperClass: static::class, expectedActionClass: static::getAction(), actualActionClass: $action::class);
        }

        return static::transform($action, $response);
    }
}
