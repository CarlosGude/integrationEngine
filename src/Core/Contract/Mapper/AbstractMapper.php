<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Mapper;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;
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
     * Validates the action and delegates to transform().
     * Called automatically by the engine — do not override.
     *
     * $headers defaults to an empty array for callers that only care about
     * the body (e.g. testing a mapper directly, outside the engine flow).
     *
     * @param array<mixed>                $response
     * @param array<string, list<string>> $headers
     *
     * @throws MapperActionMismatchException
     */
    final public static function map(
        AbstractAction $action,
        array $response,
        array $headers = [],
    ): ResponseInterface {
        if ($action::class !== static::getAction()) {
            throw new MapperActionMismatchException(mapperClass: static::class, expectedActionClass: static::getAction(), actualActionClass: $action::class);
        }

        return static::transform($action, $response, $headers);
    }

    /**
     * Transforms the raw response body and HTTP response headers into a
     * typed ResponseInterface. Implement this in your mapper — the action
     * type is guaranteed to match getAction().
     *
     * @param array<mixed>                $response
     * @param array<string, list<string>> $headers
     */
    abstract protected static function transform(
        AbstractAction $action,
        array $response,
        array $headers,
    ): ResponseInterface;
}
