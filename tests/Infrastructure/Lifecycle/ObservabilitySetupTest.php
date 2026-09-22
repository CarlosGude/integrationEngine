<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Lifecycle;

use IntegrationEngine\Core\Lifecycle\ActionCompleted;
use IntegrationEngine\Core\Lifecycle\ActionFailed;
use IntegrationEngine\Core\Lifecycle\ActionStarted;
use IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher;
use IntegrationEngine\Infrastructure\Lifecycle\ObservabilitySetup;
use IntegrationEngine\Tests\Fake\FakeLogger;
use IntegrationEngine\Tests\Fake\FakePathAction;
use IntegrationEngine\Tests\Fake\FakeTokenResponse;
use PHPUnit\Framework\TestCase;

final class ObservabilitySetupTest extends TestCase
{
    public function testLogsLifecycleMetadataAndFailuresAtTheirOwnLevel(): void
    {
        $dispatcher = new LifecycleEventDispatcher();
        $logger = new FakeLogger();
        ObservabilitySetup::register($dispatcher, $logger, ['log_level' => 'debug']);
        $action = FakePathAction::create('GET', '/orders');
        $dispatcher->dispatch(new ActionStarted($action, 'shop', 123.0));
        $dispatcher->dispatch(new ActionCompleted($action, 'shop', 124.0, new FakeTokenResponse([]), 25.5));
        $dispatcher->dispatch(new ActionFailed($action, 'shop', 125.0, new \RuntimeException('offline'), 40.0));
        self::assertSame([
            ['level' => 'debug', 'message' => 'Integration action started', 'context' => ['integration' => 'shop', 'action' => 'fake_path_action', 'timestamp' => 123.0]],
            ['level' => 'debug', 'message' => 'Integration action completed', 'context' => ['integration' => 'shop', 'action' => 'fake_path_action', 'duration_ms' => 25.5, 'response_type' => FakeTokenResponse::class]],
            ['level' => 'error', 'message' => 'Integration action failed', 'context' => ['integration' => 'shop', 'action' => 'fake_path_action', 'duration_ms' => 40.0, 'error' => 'offline', 'error_class' => \RuntimeException::class]],
        ], $logger->all());
    }

    public function testFilterAppliesToEveryObserverAndCallbacksReceiveOriginalEvents(): void
    {
        $dispatcher = new LifecycleEventDispatcher();
        $logger = new FakeLogger();
        $alerts = new FakeLogger();
        $metrics = [];
        $errors = [];
        ObservabilitySetup::register($dispatcher, $logger, [
            'integration_filter' => 'shop',
            'slow_request_threshold_ms' => '100',
            'slow_request_logger' => $alerts,
            'metrics_callback' => static function ($event) use (&$metrics): void { $metrics[] = $event; },
            'error_callback' => static function ($event) use (&$errors): void { $errors[] = $event; },
        ]);
        $action = FakePathAction::create('GET', '/orders');
        foreach (['other', 'shop'] as $integration) {
            $dispatcher->dispatch(new ActionStarted($action, $integration, 1.0));
            $completed = new ActionCompleted($action, $integration, 1.0, new FakeTokenResponse([]), 101.0);
            $failed = new ActionFailed($action, $integration, 1.0, new \RuntimeException('offline'), 101.0);
            $dispatcher->dispatch($completed);
            $dispatcher->dispatch($failed);
        }
        self::assertSame([$completed, $failed], $metrics);
        self::assertSame([$failed], $errors);
        self::assertCount(3, $logger->all());
        self::assertSame(['integration' => 'shop', 'action' => 'fake_path_action', 'timestamp' => 1.0], $logger->contextFor('info', 'started'));
        self::assertSame([['level' => 'warning', 'message' => 'Slow integration request detected', 'context' => ['integration' => 'shop', 'action' => 'fake_path_action', 'duration_ms' => 101.0, 'threshold_ms' => 100.0, 'overage_ms' => 1.0]]], $alerts->all());
    }

    public function testSlowAlertIsStrictlyAboveThresholdAndIndependentOfLogging(): void
    {
        $dispatcher = new LifecycleEventDispatcher();
        $logger = new FakeLogger();
        ObservabilitySetup::register($dispatcher, $logger, ['logging' => false, 'slow_request_threshold_ms' => 100, 'slow_request_logger' => null]);
        foreach ([99.0, 100.0, 101.0] as $duration) {
            $dispatcher->dispatch(new ActionCompleted(FakePathAction::create('GET', '/orders'), 'shop', 1.0, new FakeTokenResponse([]), $duration));
        }
        self::assertCount(1, $logger->all());
        self::assertSame(101.0, $logger->contextFor('warning', 'Slow')['duration_ms']);
    }

    public function testDefaultSlowRequestThresholdIsExactlyFiveSeconds(): void
    {
        $dispatcher = new LifecycleEventDispatcher();
        $logger = new FakeLogger();
        ObservabilitySetup::register($dispatcher, $logger, ['logging' => false]);
        foreach ([4999.0, 5000.0, 5001.0] as $duration) {
            $dispatcher->dispatch(new ActionCompleted(FakePathAction::create('GET', '/orders'), 'shop', 1.0, new FakeTokenResponse([]), $duration));
        }
        self::assertSame([['level' => 'warning', 'message' => 'Slow integration request detected', 'context' => ['integration' => 'shop', 'action' => 'fake_path_action', 'duration_ms' => 5001.0, 'threshold_ms' => 5000.0, 'overage_ms' => 1.0]]], $logger->all());
    }

    public function testInvalidOptionalConfigurationFallsBackWithoutRegisteringCallbacks(): void
    {
        $dispatcher = new LifecycleEventDispatcher();
        $logger = new FakeLogger();
        ObservabilitySetup::register($dispatcher, $logger, ['log_level' => null, 'integration_filter' => '', 'slow_request_threshold_ms' => 'invalid', 'metrics_callback' => false, 'error_callback' => false]);
        $dispatcher->dispatch(new ActionCompleted(FakePathAction::create('GET', '/orders'), 'any', 1.0, new FakeTokenResponse([]), 9000.0));
        self::assertCount(1, $logger->all());
        self::assertTrue($logger->hasEntry('info', 'completed'));
    }
}
