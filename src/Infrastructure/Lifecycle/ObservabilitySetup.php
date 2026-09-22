<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Lifecycle;

use IntegrationEngine\Core\Event\RequestFailed;
use IntegrationEngine\Core\Event\RequestSent;
use IntegrationEngine\Core\Event\ResponseMapped;
use IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Simple observability setup for lifecycle events.
 * Provides pre-built observers for logging, metrics, and alerting.
 *
 * Usage:
 *   ObservabilitySetup::register($dispatcher, [
 *       'logging' => true,
 *       'slow_request_threshold_ms' => 3000,
 *       'slack_webhook' => 'https://...',
 *   ]);
 */
final class ObservabilitySetup
{
    /**
     * Register observability observers based on configuration.
     *
     * @param array<string, mixed> $config Configuration array:
     *                                     - logging: bool (default: true) — log all actions
     *                                     - log_level: string (default: 'info') — PSR-3 level
     *                                     - slow_request_threshold_ms: float (default: 5000) — alert if slower
     *                                     - slow_request_logger: LoggerInterface (optional) — use different logger for alerts
     *                                     - metrics_callback: callable (optional) — custom metric recorder
     *                                     - error_callback: callable (optional) — custom error handler
     *                                     - integration_filter: string (optional) — only observe this integration
     */
    public static function register(
        LifecycleEventDispatcher $dispatcher,
        LoggerInterface $logger,
        array $config = [],
    ): void {
        $config = array_merge([
            'logging' => true,
            'log_level' => LogLevel::INFO,
            'slow_request_threshold_ms' => 5000,
            'slow_request_logger' => $logger,
            'integration_filter' => null,
        ], $config);

        $filter = \is_string($config['integration_filter']) && '' !== $config['integration_filter'] ? $config['integration_filter'] : null;

        if ($config['logging']) {
            $logLevel = \is_string($config['log_level']) ? $config['log_level'] : LogLevel::INFO;
            self::registerLogging($dispatcher, $logger, $logLevel, $filter);
        }

        $threshold = is_numeric($config['slow_request_threshold_ms']) ? (float) $config['slow_request_threshold_ms'] : 0.0;
        if ($threshold > 0) {
            $alertLogger = $config['slow_request_logger'] instanceof LoggerInterface ? $config['slow_request_logger'] : $logger;
            self::registerSlowRequestAlerts($dispatcher, $alertLogger, $threshold, $filter);
        }

        if (isset($config['metrics_callback']) && \is_callable($config['metrics_callback'])) {
            self::registerMetrics($dispatcher, $config['metrics_callback'], $filter);
        }

        if (isset($config['error_callback']) && \is_callable($config['error_callback'])) {
            self::registerErrorHandling($dispatcher, $config['error_callback'], $filter);
        }
    }

    /**
     * Register automatic logging for all actions.
     */
    private static function registerLogging(
        LifecycleEventDispatcher $dispatcher,
        LoggerInterface $logger,
        string $logLevel,
        ?string $filter,
    ): void {
        $dispatcher->subscribe(RequestSent::class, static function (RequestSent $e) use ($logger, $logLevel, $filter): void {
            if (null !== $filter && $e->integrationName !== $filter) {
                return;
            }
            $logger->log($logLevel, 'Integration action started', [
                'integration' => $e->integrationName,
                'action' => $e->action,
                'timestamp' => $e->timestamp,
            ]);
        });

        $dispatcher->subscribe(ResponseMapped::class, static function (ResponseMapped $e) use ($logger, $logLevel, $filter): void {
            if (null !== $filter && $e->integrationName !== $filter) {
                return;
            }
            $logger->log($logLevel, 'Integration action completed', [
                'integration' => $e->integrationName,
                'action' => $e->action,
                'duration_ms' => $e->durationMs,
                'response_type' => $e->responseClass,
            ]);
        });

        $dispatcher->subscribe(RequestFailed::class, static function (RequestFailed $e) use ($logger, $filter): void {
            if (null !== $filter && $e->integrationName !== $filter) {
                return;
            }
            $logger->error('Integration action failed', [
                'integration' => $e->integrationName,
                'action' => $e->action,
                'duration_ms' => $e->durationMs,
                'error' => $e->message,
                'error_class' => $e->exceptionClass,
            ]);
        });
    }

    /**
     * Register alerts for slow requests.
     */
    private static function registerSlowRequestAlerts(
        LifecycleEventDispatcher $dispatcher,
        LoggerInterface $alertLogger,
        float $threshold,
        ?string $filter,
    ): void {
        $dispatcher->subscribe(ResponseMapped::class, static function (ResponseMapped $e) use ($alertLogger, $threshold, $filter): void {
            if (null !== $filter && $e->integrationName !== $filter) {
                return;
            }

            if ($e->durationMs > $threshold) {
                $alertLogger->warning('Slow integration request detected', [
                    'integration' => $e->integrationName,
                    'action' => $e->action,
                    'duration_ms' => $e->durationMs,
                    'threshold_ms' => $threshold,
                    'overage_ms' => $e->durationMs - $threshold,
                ]);
            }
        });
    }

    /**
     * Register custom metrics callback.
     * Callback signature: function(ResponseMapped|RequestFailed $event): void.
     */
    private static function registerMetrics(
        LifecycleEventDispatcher $dispatcher,
        callable $metricsCallback,
        ?string $filter,
    ): void {
        $dispatcher->subscribe(ResponseMapped::class, static function (ResponseMapped $e) use ($metricsCallback, $filter): void {
            if (null !== $filter && $e->integrationName !== $filter) {
                return;
            }
            $metricsCallback($e);
        });

        $dispatcher->subscribe(RequestFailed::class, static function (RequestFailed $e) use ($metricsCallback, $filter): void {
            if (null !== $filter && $e->integrationName !== $filter) {
                return;
            }
            $metricsCallback($e);
        });
    }

    /**
     * Register custom error callback.
     * Callback signature: function(RequestFailed $event): void.
     */
    private static function registerErrorHandling(
        LifecycleEventDispatcher $dispatcher,
        callable $errorCallback,
        ?string $filter,
    ): void {
        $dispatcher->subscribe(RequestFailed::class, static function (RequestFailed $e) use ($errorCallback, $filter): void {
            if (null !== $filter && $e->integrationName !== $filter) {
                return;
            }
            $errorCallback($e);
        });
    }
}
