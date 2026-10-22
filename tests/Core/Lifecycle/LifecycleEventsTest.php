<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core\Lifecycle;

use IntegrationEngine\Core\Batch\EngineRequest;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Auth\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Contract\Client\ClientInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use IntegrationEngine\Core\Event\RequestFailed;
use IntegrationEngine\Core\Event\RequestSent;
use IntegrationEngine\Core\Event\ResponseMapped;
use IntegrationEngine\Core\Event\ResponseReceived;
use IntegrationEngine\Core\Event\TokenRefreshed;
use IntegrationEngine\Core\Exception\NotMappedActionException;
use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher;
use IntegrationEngine\Tests\Fake\FakeCache;
use IntegrationEngine\Tests\Fake\FakeClient;
use IntegrationEngine\Tests\Fake\FakeConfigPort;
use IntegrationEngine\Tests\Fake\FakeProtectedAction;
use IntegrationEngine\Tests\Fake\FakeTokenAction;
use IntegrationEngine\Tests\Fake\FakeUnmappedTokenAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LifecycleEventsTest extends TestCase
{
    /** @var list<object> */
    private array $events = [];

    #[Test]
    public function directSendDispatchesEventsInLifecycleOrder(): void
    {
        $this->engine(new FakeClient())->send(FakeTokenAction::getName());

        self::assertSame([RequestSent::class, ResponseReceived::class, ResponseMapped::class], $this->eventClasses());
    }

    #[Test]
    public function receivedAndMappedCarryTheStatusCodeReportedByTheClient(): void
    {
        $this->engine($this->clientReporting(201))->send(FakeTokenAction::getName());

        self::assertSame(201, $this->single(ResponseReceived::class)->statusCode);
        self::assertSame(201, $this->single(ResponseMapped::class)->statusCode);
    }

    #[Test]
    public function batchItemsCarryTheStatusCodeReportedByTheClient(): void
    {
        $this->engine($this->clientReporting(201))->sendMany(['ok' => new EngineRequest(FakeTokenAction::getName())]);

        self::assertSame(201, $this->single(ResponseReceived::class)->statusCode);
        self::assertSame(201, $this->single(ResponseMapped::class)->statusCode);
    }

    #[Test]
    public function batchItemsReportZeroWhenTheClientReportsNoStatusCode(): void
    {
        $this->engine(new FakeClient())->sendMany(['ok' => new EngineRequest(FakeTokenAction::getName())]);

        self::assertSame(0, $this->single(ResponseReceived::class)->statusCode);
        self::assertSame(0, $this->single(ResponseMapped::class)->statusCode);
    }

    #[Test]
    public function responseMappedReportsZeroWhenTheClientReportsNoStatusCode(): void
    {
        $this->engine(new FakeClient())->send(FakeTokenAction::getName());

        self::assertSame(0, $this->single(ResponseMapped::class)->statusCode);
    }

    #[Test]
    public function mappingFailureIsReportedAfterTheResponseWasReceived(): void
    {
        try {
            $this->engine(new FakeClient())->send(FakeUnmappedTokenAction::getName());
            self::fail('Expected NotMappedActionException.');
        } catch (NotMappedActionException) {
        }

        self::assertSame([RequestSent::class, ResponseReceived::class, RequestFailed::class], $this->eventClasses());
    }

    #[Test]
    public function transportFailureIsReportedWithoutAReceivedResponse(): void
    {
        $client = new FakeClient();
        $client->queueException(FakeTokenAction::getName(), new RequestResponseException(statusCode: 503, context: 'down'));

        try {
            $this->engine($client)->send(FakeTokenAction::getName());
            self::fail('Expected RequestResponseException.');
        } catch (RequestResponseException) {
        }

        self::assertSame([RequestSent::class, RequestFailed::class], $this->eventClasses());
    }

    #[Test]
    public function dynamicAuthRetryReportsOnlyTheResponseThatReachesTheMapper(): void
    {
        $cache = new FakeCache();
        $cache->set($this->protectedAuth()->cacheKey('test_integration'), 'stale_token', 60);
        $client = new FakeClient();
        $client->setResponse(FakeTokenAction::getName(), ['access_token' => 'fresh_token']);
        $client->queueException(FakeProtectedAction::getName(), new RequestResponseException(statusCode: 401, context: 'token revoked'));

        $this->engine($client, $cache)->send(FakeProtectedAction::getName());

        self::assertSame(
            [RequestSent::class, TokenRefreshed::class, ResponseReceived::class, ResponseMapped::class],
            $this->eventClasses(),
        );
    }

    #[Test]
    public function batchItemsReportTheirOwnReceivedResponseBeforeMapping(): void
    {
        $results = $this->engine(new FakeClient())->sendMany([
            'ok' => new EngineRequest(FakeTokenAction::getName()),
            'unmapped' => new EngineRequest(FakeUnmappedTokenAction::getName()),
        ]);

        self::assertFalse($results['unmapped']->isSuccess());
        self::assertSame(
            [
                [RequestSent::class, 'ok'],
                [RequestSent::class, 'unmapped'],
                [ResponseReceived::class, 'ok'],
                [ResponseMapped::class, 'ok'],
                [ResponseReceived::class, 'unmapped'],
                [RequestFailed::class, 'unmapped'],
            ],
            array_map(
                static fn (object $event): array => [$event::class, property_exists($event, 'requestKey') ? $event->requestKey : null],
                $this->events,
            ),
        );
    }

    private function engine(ClientInterface $client, FakeCache $cache = new FakeCache()): IntegrationEngine
    {
        $config = new FakeConfigPort();
        $config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $config->register(FakeUnmappedTokenAction::getName(), FakeUnmappedTokenAction::create('GET', '/unmapped'));
        $config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, $this->protectedAuth()));

        $dispatcher = new LifecycleEventDispatcher();
        foreach ([RequestSent::class, TokenRefreshed::class, ResponseReceived::class, ResponseMapped::class, RequestFailed::class] as $eventClass) {
            $dispatcher->subscribe($eventClass, function (object $event): void {
                $this->events[] = $event;
            });
        }

        return new IntegrationEngine(
            config: $config,
            client: $client,
            cache: $cache,
            integrationName: 'test_integration',
            eventDispatcher: $dispatcher,
        );
    }

    private function clientReporting(int $statusCode): ClientInterface
    {
        return new class($statusCode) implements ClientInterface {
            public function __construct(private readonly int $statusCode) {}

            public function send(AbstractAction $action, ?ActionContextInterface $context = null, ?RequestHeadersInterface $headers = null): array
            {
                return ['body' => ['token' => 'abc'], 'headers' => [], 'statusCode' => $this->statusCode];
            }
        };
    }

    private function protectedAuth(): DynamicAuthorizationConfig
    {
        return new DynamicAuthorizationConfig(action: FakeTokenAction::getName(), tokenField: 'access_token', ttl: 60);
    }

    /** @return list<class-string> */
    private function eventClasses(): array
    {
        return array_map(static fn (object $event): string => $event::class, $this->events);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $eventClass
     *
     * @return T
     */
    private function single(string $eventClass): object
    {
        $matches = array_values(array_filter(
            $this->events,
            static fn (object $event): bool => $event instanceof $eventClass,
        ));
        self::assertCount(1, $matches);
        self::assertInstanceOf($eventClass, $matches[0]);

        return $matches[0];
    }
}
