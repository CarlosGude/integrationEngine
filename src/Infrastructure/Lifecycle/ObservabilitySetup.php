<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Lifecycle;

use IntegrationEngine\Core\Lifecycle\ActionCompleted;
use IntegrationEngine\Core\Lifecycle\ActionFailed;
use IntegrationEngine\Core\Lifecycle\ActionStarted;
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

        if ($config['logging']) {
            self::registerLogging($dispatcher, $logger, $config);
        }

        if (isset($config['slow_request_threshold_ms']) && $config['slow_request_threshold_ms'] > 0) {
            self::registerSlowRequestAlerts($dispatcher, $config['slow_request_logger'], $config);
        }

        if (isset($config['metrics_callback']) && \is_callable($config['metrics_callback'])) {
            self::registerMetrics($dispatcher, $config['metrics_callback'], $config);
        }

        if (isset($config['error_callback']) && \is_callable($config['error_callback'])) {
            self::registerErrorHandling($dispatcher, $config['error_callback'], $config);
        }
    }

    /**
     * Register automatic logging for all actions.
     */
    private static function registerLogging(
        LifecycleEventDispatcher $dispatcher,
        LoggerInterface $logger,
        array $config,
    ): void {
        $logLevel = $config['log_level'];
        $filter = $config['integration_filter'];

        $dispatcher->subscribe(ActionStarted::class, static function (ActionStarted $e) use ($logger, $logLevel, $filter): void {
            if ($filter && $e->integrationName() !== $filter) {
                return;
            }
            $logger->log($logLevel, 'Integration action started', [
                'integration' => $e->integrationName(),
                'action' => $e->action()->getName(),
                'timestamp' => $e->timestamp(),
            ]);
        });

        $dispatcher->subscribe(ActionCompleted::class, static function (ActionCompleted $e) use ($logger, $logLevel, $filter): void {
            if ($filter && $e->integrationName() !== $filter) {
                return;
            }
            $logger->log($logLevel, 'Integration action completed', [
                'integration' => $e->integrationName(),
                'action' => $e->action()->getName(),
                'duration_ms' => $e->durationMs(),
                'response_type' => $e->response()::class,
            ]);
        });

        $dispatcher->subscribe(ActionFailed::class, static function (ActionFailed $e) use ($logger, $filter): void {
            if ($filter && $e->integrationName() !== $filter) {
                return;
            }
            $logger->error('Integration action failed', [
                'integration' => $e->integrationName(),
                'action' => $e->action()->getName(),
                'duration_ms' => $e->durationMs(),
                'error' => $e->error()->getMessage(),
                'error_class' => $e->error()::class,
            ]);
        });
    }

    /**
     * Register alerts for slow requests.
     */
    private static function registerSlowRequestAlerts(
        LifecycleEventDispatcher $dispatcher,
        LoggerInterface $alertLogger,
        array $config,
    ): void {
        $threshold = $config['slow_request_threshold_ms'];
        $filter = $config['integration_filter'];

        $dispatcher->subscribe(ActionCompleted::class, static function (ActionCompleted $e) use ($alertLogger, $threshold, $filter): void {
            if ($filter && $e->integrationName() !== $filter) {
                return;
            }

            if ($e->durationMs() > $threshold) {
                $alertLogger->warning('Slow integration request detected', [
                    'integration' => $e->integrationName(),
                    'action' => $e->action()->getName(),
                    'duration_ms' => $e->durationMs(),
                    'threshold_ms' => $threshold,
                    'overage_ms' => $e->durationMs() - $threshold,
                ]);
            }
        });
    }

    /**
     * Register custom metrics callback.
     * Callback signature: function(ActionCompleted|ActionFailed $event): void.
     */
    private static function registerMetrics(
        LifecycleEventDispatcher $dispatcher,
        callable $metricsCallback,
        array $config,
    ): void {
        $filter = $config['integration_filter'];

        $dispatcher->subscribe(ActionCompleted::class, static function (ActionCompleted $e) use ($metricsCallback, $filter): void {
            if ($filter && $e->integrationName() !== $filter) {
                return;
            }
            $metricsCallback($e);
        });

        $dispatcher->subscribe(ActionFailed::class, static function (ActionFailed $e) use ($metricsCallback, $filter): void {
            if ($filter && $e->integrationName() !== $filter) {
                return;
            }
            $metricsCallback($e);
        });
    }

    /**
     * Register custom error callback.
     * Callback signature: function(ActionFailed $event): void.
     */
    private static function registerErrorHandling(
        LifecycleEventDispatcher $dispatcher,
        callable $errorCallback,
        array $config,
    ): void {
        $filter = $config['integration_filter'];

        $dispatcher->subscribe(ActionFailed::class, static function (ActionFailed $e) use ($errorCallback, $filter): void {
            if ($filter && $e->integrationName() !== $filter) {
                return;
            }
            $errorCallback($e);
        });
    }
}
