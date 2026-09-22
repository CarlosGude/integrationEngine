<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core\Lifecycle;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Client\ClientInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Core\Event\RequestSent;
use IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher;
use IntegrationEngine\Core\Event\ResponseMapped;
use IntegrationEngine\Tests\Fake\FakeCache;
use IntegrationEngine\Tests\Fake\FakeClient;
use IntegrationEngine\Tests\Fake\FakeConfigPort;
use IntegrationEngine\Tests\Fake\FakeTokenAction;
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

        self::assertSame(
            [RequestSent::class, ResponseMapped::class],
            array_map(static fn (object $event): string => $event::class, $this->events),
        );
    }

    #[Test]
    public function responseMappedCarriesTheStatusCodeReportedByTheClient(): void
    {
        $client = new class implements ClientInterface {
            public function send(AbstractAction $action, ?ActionContextInterface $context = null, ?RequestHeadersInterface $headers = null): array
            {
                return ['body' => ['token' => 'abc'], 'headers' => [], 'statusCode' => 201];
            }
        };

        $this->engine($client)->send(FakeTokenAction::getName());

        self::assertSame(201, $this->responseMapped()->statusCode);
    }

    #[Test]
    public function responseMappedReportsZeroWhenTheClientReportsNoStatusCode(): void
    {
        $this->engine(new FakeClient())->send(FakeTokenAction::getName());

        self::assertSame(0, $this->responseMapped()->statusCode);
    }

    private function engine(ClientInterface $client): IntegrationEngine
    {
        $config = new FakeConfigPort();
        $config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));

        $dispatcher = new LifecycleEventDispatcher();
        foreach ([RequestSent::class, ResponseMapped::class] as $eventClass) {
            $dispatcher->subscribe($eventClass, function (object $event): void {
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

    private function responseMapped(): ResponseMapped
    {
        $matches = array_values(array_filter(
            $this->events,
            static fn (object $event): bool => $event instanceof ResponseMapped,
        ));
        self::assertCount(1, $matches);

        return $matches[0];
    }
}
