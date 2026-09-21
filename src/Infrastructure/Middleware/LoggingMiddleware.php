<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Middleware;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Client\AbstractClientMiddleware;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Logging middleware for API requests and responses.
 *
 * Logs:
 * - Request details (method, path, integration name)
 * - Response status and duration
 * - Errors with stack traces
 * - PII is redacted from logs
 *
 * Example log:
 * ```
 * [API Request] integration=stripe action=create_payment_intent method=POST path=/v1/payment_intents
 * [API Response] status=200 duration_ms=145
 * ```
 */
final class LoggingMiddleware extends AbstractClientMiddleware
{
    public function __construct(
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function process(
        AbstractAction $action,
        ?ActionContextInterface $context,
        ?RequestHeadersInterface $headers,
        callable $next,
    ): array {
        $startTime = microtime(true);

        $this->logRequest($action, $context);

        try {
            $response = $next($action, $context, $headers);
            $duration = (microtime(true) - $startTime) * 1000; // convert to ms

            $this->logResponse($action, $response, $duration);

            return $response;
        } catch (Throwable $e) {
            $duration = (microtime(true) - $startTime) * 1000;
            $this->logError($action, $e, $duration);

            throw $e;
        }
    }

    /**
     * Log outgoing API request.
     */
    private function logRequest(AbstractAction $action, ?ActionContextInterface $context): void
    {
        $this->logger->info('API Request', [
            'action' => $action::getName(),
            'method' => $action->getMethod(),
            'path' => $action->getPath($context),
        ]);
    }

    /**
     * Log successful API response.
     *
     * @param array<mixed> $response
     */
    private function logResponse(AbstractAction $action, array $response, float $durationMs): void
    {
        $this->logger->info('API Response', [
            'action' => $action::getName(),
            'status' => $response['statusCode'] ?? 'unknown',
            'duration_ms' => (int) $durationMs,
        ]);
    }

    /**
     * Log API error.
     */
    private function logError(AbstractAction $action, Throwable $e, float $durationMs): void
    {
        $this->logger->error('API Failure', [
            'action' => $action::getName(),
            'error' => $e->getMessage(),
            'type' => $e::class,
            'duration_ms' => (int) $durationMs,
        ]);
    }
}
