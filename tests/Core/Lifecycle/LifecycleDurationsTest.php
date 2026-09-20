<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core\Lifecycle;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Client\ClientInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Core\Lifecycle\ActionCompleted;
use IntegrationEngine\Core\Lifecycle\ActionFailed;
use IntegrationEngine\Core\Lifecycle\HttpResponseReceived;
use IntegrationEngine\Core\Lifecycle\IntegrationEngineEvent;
use IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher;
use IntegrationEngine\Core\Lifecycle\ResponseMapped;
use IntegrationEngine\Tests\Fake\FakeCache;
use IntegrationEngine\Tests\Fake\FakeConfigPort;
use IntegrationEngine\Tests\Fake\FakeSlowAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The durations carried by the lifecycle events, asserted by order of
 * magnitude: the client and the mapper both take a known minimum time, so a
 * duration below a millisecond (or above a minute) means the engine is not
 * measuring an elapsed time in milliseconds at all.
 */
final class LifecycleDurationsTest extends TestCase
{
    private const CLIENT_DELAY_MICROSECONDS = 20_000;

    /** A duration in milliseconds is at least this much: both delays are 20 ms. */
    private const MIN_MS = 1.0;

    /** …and nothing here takes a minute. */
    private const MAX_MS = 60_000.0;

    /** @var list<IntegrationEngineEvent> */
    private array $events = [];

    #[Test]
    public function httpResponseReceivedMeasuresTheClientCallInMilliseconds(): void
    {
        $this->engine($this->slowClient())->send(FakeSlowAction::getName());

        $event = $this->event(HttpResponseReceived::class);
        self::assertInstanceOf(HttpResponseReceived::class, $event);
        self::assertGreaterThan(self::MIN_MS, $event->durationMs());
        self::assertLessThan(self::MAX_MS, $event->durationMs());
    }

    #[Test]
    public function responseMappedMeasuresHttpMappingAndTotalInMilliseconds(): void
    {
        $this->engine($this->slowClient())->send(FakeSlowAction::getName());

        $event = $this->event(ResponseMapped::class);
        self::assertInstanceOf(ResponseMapped::class, $event);

        foreach ([$event->httpDurationMs(), $event->mappingDurationMs(), $event->totalDurationMs()] as $duration) {
            self::assertGreaterThan(self::MIN_MS, $duration);
            self::assertLessThan(self::MAX_MS, $duration);
        }
    }

    #[Test]
    public function actionCompletedMeasuresTheWholeSendInMilliseconds(): void
    {
        $this->engine($this->slowClient())->send(FakeSlowAction::getName());

        $event = $this->event(ActionCompleted::class);
        self::assertInstanceOf(ActionCompleted::class, $event);
        self::assertGreaterThan(self::MIN_MS, $event->durationMs());
        self::assertLessThan(self::MAX_MS, $event->durationMs());
    }

    #[Test]
    public function actionFailedIsDispatchedWithTheErrorAndItsDuration(): void
    {
        $failure = new \RuntimeException('upstream is down');

        try {
            $this->engine($this->slowClient($failure))->send(FakeSlowAction::getName());
            self::fail('The engine must rethrow the client failure.');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }

        $event = $this->event(ActionFailed::class);
        self::assertInstanceOf(ActionFailed::class, $event);
        self::assertSame($failure, $event->error());
        self::assertSame('test_integration', $event->integrationName());
        self::assertGreaterThan(self::MIN_MS, $event->durationMs());
        self::assertLessThan(self::MAX_MS, $event->durationMs());
    }

    /**
     * Spends a known amount of time before answering (or failing), so the
     * durations the engine reports are comparable against it.
     */
    private function slowClient(?\Throwable $failWith = null): ClientInterface
    {
        return new class(self::CLIENT_DELAY_MICROSECONDS, $failWith) implements ClientInterface {
            public function __construct(
                private readonly int $delayMicroseconds,
                private readonly ?\Throwable $failWith,
            ) {}

            public function send(AbstractAction $action, ?ActionContextInterface $context = null, ?RequestHeadersInterface $headers = null): array
            {
                usleep($this->delayMicroseconds);

                if (null !== $this->failWith) {
                    throw $this->failWith;
                }

                return ['body' => ['token' => 'abc'], 'headers' => []];
            }
        };
    }

    private function engine(ClientInterface $client): IntegrationEngine
    {
        $config = new FakeConfigPort();
        $config->register(FakeSlowAction::getName(), FakeSlowAction::create('GET', '/slow'));

        $dispatcher = new LifecycleEventDispatcher();
        foreach ([HttpResponseReceived::class, ResponseMapped::class, ActionCompleted::class, ActionFailed::class] as $eventClass) {
            $dispatcher->subscribe($eventClass, function (IntegrationEngineEvent $event): void {
                $this->events[] = $event;
            });
        }

        return new IntegrationEngine(
            config: $config,
            client: $client,
            cache: new FakeCache(),
            integrationName: 'test_integration',
            eventDispatcher: $dispatcher,
        );
    }

    /** @param class-string<IntegrationEngineEvent> $eventClass */
    private function event(string $eventClass): IntegrationEngineEvent
    {
        $matches = array_values(array_filter(
            $this->events,
            static fn (IntegrationEngineEvent $event): bool => $event::class === $eventClass,
        ));
        self::assertCount(1, $matches, \sprintf('Expected exactly one %s.', $eventClass));

        return $matches[0];
    }
}
