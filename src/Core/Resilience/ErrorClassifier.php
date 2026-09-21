<?php

declare(strict_types=1);

namespace CarlosgudeSdk\IntegrationEngine\Core\Resilience;

use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Classify errors as transient (retryable) or permanent (fail-fast).
 *
 * Transient errors (retry):
 * - 429 Too Many Requests (rate limit)
 * - 503 Service Unavailable
 * - 504 Gateway Timeout
 * - Network timeouts
 * - Connection refused
 *
 * Permanent errors (fail fast):
 * - 400 Bad Request
 * - 401 Unauthorized
 * - 403 Forbidden
 * - 404 Not Found
 * - 405 Method Not Allowed
 */
final class ErrorClassifier
{
    /**
     * Is this error transient and retryable?
     */
    public static function isTransient(Throwable $e): bool
    {
        // Network errors are always transient
        if (self::isNetworkError($e)) {
            return true;
        }

        // HTTP 5xx errors are transient
        if (self::isServerError($e)) {
            return true;
        }

        // HTTP 429 (rate limit) is transient
        if (self::isRateLimitError($e)) {
            return true;
        }

        // HTTP 408 (request timeout) is transient
        if (self::isTimeoutError($e)) {
            return true;
        }

        return false;
    }

    /**
     * Is this error permanent (no point retrying)?
     */
    public static function isPermanent(Throwable $e): bool
    {
        // HTTP 4xx errors are permanent (except 408, 429)
        if (self::isClientError($e)) {
            return true;
        }

        // Authentication errors are permanent
        if (self::isAuthenticationError($e)) {
            return true;
        }

        return false;
    }

    /**
     * Get HTTP status code if available.
     */
    public static function getStatusCode(Throwable $e): ?int
    {
        if ($e instanceof HttpExceptionInterface) {
            return $e->getResponse()->getStatusCode();
        }

        return null;
    }

    /**
     * Network-level errors (transport).
     */
    private static function isNetworkError(Throwable $e): bool
    {
        return $e instanceof TransportExceptionInterface;
    }

    /**
     * HTTP 5xx (server errors).
     */
    private static function isServerError(Throwable $e): bool
    {
        if (!($e instanceof HttpExceptionInterface)) {
            return false;
        }

        $code = $e->getResponse()->getStatusCode();

        return $code >= 500 && $code < 600;
    }

    /**
     * HTTP 429 (rate limit).
     */
    private static function isRateLimitError(Throwable $e): bool
    {
        if (!($e instanceof HttpExceptionInterface)) {
            return false;
        }

        return 429 === $e->getResponse()->getStatusCode();
    }

    /**
     * HTTP 408 (request timeout).
     */
    private static function isTimeoutError(Throwable $e): bool
    {
        if (!($e instanceof HttpExceptionInterface)) {
            return false;
        }

        return 408 === $e->getResponse()->getStatusCode();
    }

    /**
     * HTTP 4xx (client errors, except 408 and 429).
     */
    private static function isClientError(Throwable $e): bool
    {
        if (!($e instanceof HttpExceptionInterface)) {
            return false;
        }

        $code = $e->getResponse()->getStatusCode();

        // 408 and 429 are handled elsewhere
        if (408 === $code || 429 === $code) {
            return false;
        }

        return $code >= 400 && $code < 500;
    }

    /**
     * HTTP 401 (unauthorized).
     */
    private static function isAuthenticationError(Throwable $e): bool
    {
        if (!($e instanceof HttpExceptionInterface)) {
            return false;
        }

        return 401 === $e->getResponse()->getStatusCode();
    }
}
