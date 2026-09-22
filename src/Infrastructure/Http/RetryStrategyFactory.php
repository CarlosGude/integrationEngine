<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Http;

use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;

final class RetryStrategyFactory
{
    /** @param array{status_codes?: list<int>, retry_non_idempotent?: bool, delay_ms?: int, multiplier?: float, max_delay_ms?: int, jitter?: float, max_retries?: int} $retryConfig */
    public static function create(array $retryConfig): GenericRetryStrategy
    {
        $codes = [0, ...($retryConfig['status_codes'] ?? [423, 425, 429, 500, 502, 503, 504, 507, 510])];
        $statusCodes = ($retryConfig['retry_non_idempotent'] ?? false)
            ? $codes
            : array_fill_keys($codes, ['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS', 'TRACE']);

        return new GenericRetryStrategy(
            $statusCodes,
            $retryConfig['delay_ms'] ?? 200,
            $retryConfig['multiplier'] ?? 2.0,
            $retryConfig['max_delay_ms'] ?? 2000,
            $retryConfig['jitter'] ?? 0.1,
        );
    }
}
