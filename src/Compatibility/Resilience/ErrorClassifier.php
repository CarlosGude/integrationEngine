<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Resilience;

use IntegrationEngine\Infrastructure\Resilience\SymfonyErrorClassifier;

/**
 * Legacy API, loaded through Composer's explicit classmap, outside the Core layer.
 *
 * @deprecated use EngineErrorClassifier or Infrastructure\Resilience\SymfonyErrorClassifier
 */
final class ErrorClassifier
{
    public static function isTransient(\Throwable $e): bool
    {
        return (new SymfonyErrorClassifier())->classify($e)->isTransient();
    }

    public static function isPermanent(\Throwable $e): bool
    {
        return (new SymfonyErrorClassifier())->classify($e)->isPermanent();
    }

    public static function getStatusCode(\Throwable $e): ?int
    {
        return (new SymfonyErrorClassifier())->classify($e)->statusCode;
    }
}
