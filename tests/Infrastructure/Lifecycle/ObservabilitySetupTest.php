<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Lifecycle;

use IntegrationEngine\Core\Event\RequestFailed;
use IntegrationEngine\Core\Event\RequestSent;
use IntegrationEngine\Core\Event\ResponseMapped;
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
        $dispatcher->dispatch(new RequestSent('shop', $action::getName(), 'GET', '/orders', 123.0));
        $dispatcher->dispatch(new ResponseMapped('shop', $action::getName(), 25.5, 200, FakeTokenResponse::class, 124.0));
        $dispatcher->dispatch(new RequestFailed('shop', $action::getName(), 40.0, 0, \RuntimeException::class, 'offline', 125.0));
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
            $dispatcher->dispatch(new RequestSent($integration, $action::getName(), 'GET', '/orders', 1.0));
            $completed = new ResponseMapped($integration, $action::getName(), 101.0, 200, FakeTokenResponse::class, 1.0);
            $failed = new RequestFailed($integration, $action::getName(), 101.0, 0, \RuntimeException::class, 'Integration request failed.', 1.0);
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
            $dispatcher->dispatch(new ResponseMapped('shop', FakePathAction::getName(), $duration, 200, FakeTokenResponse::class, 1.0));
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
            $dispatcher->dispatch(new ResponseMapped('shop', FakePathAction::getName(), $duration, 200, FakeTokenResponse::class, 1.0));
        }
        self::assertSame([['level' => 'warning', 'message' => 'Slow integration request detected', 'context' => ['integration' => 'shop', 'action' => 'fake_path_action', 'duration_ms' => 5001.0, 'threshold_ms' => 5000.0, 'overage_ms' => 1.0]]], $logger->all());
    }

    public function testInvalidOptionalConfigurationFallsBackWithoutRegisteringCallbacks(): void
    {
        $dispatcher = new LifecycleEventDispatcher();
        $logger = new FakeLogger();
        ObservabilitySetup::register($dispatcher, $logger, ['log_level' => null, 'integration_filter' => '', 'slow_request_threshold_ms' => 'invalid', 'metrics_callback' => false, 'error_callback' => false]);
        $dispatcher->dispatch(new ResponseMapped('any', FakePathAction::getName(), 9000.0, 200, FakeTokenResponse::class, 1.0));
        self::assertCount(1, $logger->all());
        self::assertTrue($logger->hasEntry('info', 'completed'));
    }
}
