<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Client;

use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Client\AbstractClientMiddleware;
use IntegrationEngine\Core\Contract\Client\BatchClientInterface;
use IntegrationEngine\Core\Contract\Client\ClientInterface;
use IntegrationEngine\Core\Contract\Client\DynamicBaseUrlClientInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use IntegrationEngine\Infrastructure\Client\MiddlewareClient;
use IntegrationEngine\Tests\Fake\FakeBatchClient;
use IntegrationEngine\Tests\Fake\FakeClient;
use IntegrationEngine\Tests\Fake\FakePathAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MiddlewareClientTest extends TestCase
{
    // ── send() chains middlewares outermost-first ─────────────────────────────

    #[Test]
    public function sendCallsMiddlewaresInOrderAndDelegatesToInner(): void
    {
        $inner = new FakeClient();
        $inner->setResponse(FakePathAction::getName(), ['result' => 1]);
        $log = [];

        $client = new MiddlewareClient($inner, [
            new SpyMiddleware('A', $log),
            new SpyMiddleware('B', $log),
        ]);

        $result = $client->send(FakePathAction::create('GET', '/items'));

        self::assertSame(['result' => 1], $result);
        self::assertSame(['A:before', 'B:before', 'B:after', 'A:after'], $log);
    }

    #[Test]
    public function sendWithNoMiddlewaresDelegatesToInnerDirectly(): void
    {
        $inner = new FakeClient();
        $inner->setResponse(FakePathAction::getName(), ['x' => 1]);
        $client = new MiddlewareClient($inner, []);

        self::assertSame(['x' => 1], $client->send(FakePathAction::create('GET', '/items')));
    }

    // ── sendMany() uses inner batch when available ────────────────────────────

    #[Test]
    public function sendManyUsesBatchClientWhenInnerSupportsBatch(): void
    {
        $inner = new FakeBatchClient();
        $inner->inner()->setResponse(FakePathAction::getName(), ['ok' => true]);
        $client = new MiddlewareClient($inner, []);

        $results = $client->sendMany([
            'a' => new PreparedRequest(FakePathAction::create('GET', '/items'), null, null),
        ]);

        self::assertSame(['ok' => true], $results['a']);
        self::assertSame(1, $inner->batchCount());
    }

    #[Test]
    public function sendManyFallsBackToSequentialWhenInnerHasNoBatch(): void
    {
        $inner = new FakeClient();
        $inner->setResponse(FakePathAction::getName(), ['ok' => true]);
        $client = new MiddlewareClient($inner, []);

        $results = $client->sendMany([
            'a' => new PreparedRequest(FakePathAction::create('GET', '/items'), null, null),
            'b' => new PreparedRequest(FakePathAction::create('GET', '/items'), null, null),
        ]);

        self::assertSame(['ok' => true], $results['a']);
        self::assertSame(['ok' => true], $results['b']);
        self::assertSame(2, $inner->callCount(FakePathAction::getName()));
    }

    #[Test]
    public function sendManySequentialFallbackCapturesThrowablesPerKey(): void
    {
        $inner = new FakeClient();
        $inner->queueException(FakePathAction::getName(), new \RuntimeException('boom'));
        $client = new MiddlewareClient($inner, []);

        $results = $client->sendMany([
            'a' => new PreparedRequest(FakePathAction::create('GET', '/items'), null, null),
        ]);

        self::assertInstanceOf(\RuntimeException::class, $results['a']);
    }

    // ── always implements BatchClientInterface ────────────────────────────────

    #[Test]
    public function implementsAllRequiredInterfaces(): void
    {
        $client = new MiddlewareClient(new FakeClient(), []);

        self::assertInstanceOf(ClientInterface::class, $client);
        self::assertInstanceOf(BatchClientInterface::class, $client);
        self::assertInstanceOf(DynamicBaseUrlClientInterface::class, $client);
    }

    // ── withBaseUrl ───────────────────────────────────────────────────────────

    #[Test]
    public function withBaseUrlPropagatesInnerAndReusesMiddlewares(): void
    {
        $inner = new FakeClient();
        $client = new MiddlewareClient($inner, []);

        $resolved = $client->withBaseUrl('https://tenant.example.com');

        self::assertNotSame($client, $resolved);
        $resolved->send(FakePathAction::create('GET', '/items'));
        self::assertSame('https://tenant.example.com', $inner->lastBaseUrl());
    }

    #[Test]
    public function withBaseUrlIsNoOpWhenInnerDoesNotSupportIt(): void
    {
        $inner = new StaticOnlyClient();
        $client = new MiddlewareClient($inner, []);

        self::assertSame($client, $client->withBaseUrl('https://tenant.example.com'));
    }
}

// ── Fixtures ──────────────────────────────────────────────────────────────────

/** Records before/after call order to verify middleware chaining. */
final class SpyMiddleware extends AbstractClientMiddleware
{
    /** @param list<string> $log */
    public function __construct(private readonly string $name, private array &$log) {}

    public function process(
        AbstractAction $action,
        ?ActionContextInterface $context,
        ?RequestHeadersInterface $headers,
        callable $next,
    ): array {
        $this->log[] = "{$this->name}:before";
        $result = $next($action, $context, $headers);
        $this->log[] = "{$this->name}:after";

        return $result;
    }
}

final class StaticOnlyClient implements ClientInterface
{
    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
        return [];
    }
}
