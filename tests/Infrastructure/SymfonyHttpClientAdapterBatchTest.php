<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Exception\PathResolutionException;
use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use IntegrationEngine\Tests\Fake\FakeContext;
use IntegrationEngine\Tests\Fake\FakePathAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

final class SymfonyHttpClientAdapterBatchTest extends TestCase
{
    #[Test]
    public function sendManyDispatchesAllRequestsBeforeConsumingAnyResponse(): void
    {
        $spy = new BatchSpyHttpClient([
            ['content' => '{"n":1}'],
            ['content' => '{"n":2}'],
        ]);
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $adapter->sendMany([
            'a' => new PreparedRequest(FakePathAction::create('GET', '/first'), null, null),
            'b' => new PreparedRequest(FakePathAction::create('GET', '/second'), null, null),
        ]);

        self::assertSame(
            ['request /first', 'request /second', 'consume /first', 'consume /second'],
            $spy->log,
        );
    }

    #[Test]
    public function sendManyReturnsRawPayloadsKeyedByInput(): void
    {
        $spy = new BatchSpyHttpClient([
            ['content' => '{"id":1}'],
            ['content' => '{"id":2}'],
        ]);
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $results = $adapter->sendMany([
            'first' => new PreparedRequest(FakePathAction::create('GET', '/orders/1'), null, null),
            'second' => new PreparedRequest(FakePathAction::create('GET', '/orders/2'), null, null),
        ]);

        self::assertSame(['first', 'second'], array_keys($results));
        self::assertSame(['id' => 1], $results['first']);
        self::assertSame(['id' => 2], $results['second']);
        self::assertSame(
            ['https://api.example.com/orders/1', 'https://api.example.com/orders/2'],
            $spy->urls,
        );
    }

    #[Test]
    public function sendManyTurnsHttpErrorIntoExceptionValueWithoutAbortingOthers(): void
    {
        $spy = new BatchSpyHttpClient([
            ['status' => 500, 'content' => 'server exploded'],
            ['content' => '{"ok":true}'],
        ]);
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $results = $adapter->sendMany([
            'broken' => new PreparedRequest(FakePathAction::create('GET', '/broken'), null, null),
            'ok' => new PreparedRequest(FakePathAction::create('GET', '/healthy'), null, null),
        ]);

        $error = $results['broken'];
        self::assertInstanceOf(RequestResponseException::class, $error);
        self::assertSame(500, $error->statusCode);
        self::assertStringContainsString('GET /broken returned HTTP 500: server exploded', $error->getMessage());
        self::assertSame(['ok' => true], $results['ok']);
    }

    #[Test]
    public function sendManyTurnsRequestThrowIntoNetworkErrorValue(): void
    {
        $spy = new BatchSpyHttpClient([
            ['throwOnRequest' => new \RuntimeException('connection refused')],
            ['content' => '{"ok":true}'],
        ]);
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $results = $adapter->sendMany([
            'down' => new PreparedRequest(FakePathAction::create('GET', '/down'), null, null),
            'ok' => new PreparedRequest(FakePathAction::create('GET', '/healthy'), null, null),
        ]);

        $error = $results['down'];
        self::assertInstanceOf(RequestResponseException::class, $error);
        self::assertSame(0, $error->statusCode);
        self::assertStringContainsString('Network error on GET /down: connection refused', $error->getMessage());
        self::assertSame(['ok' => true], $results['ok']);
    }

    #[Test]
    public function sendManyTurnsConsumeThrowIntoNetworkErrorValue(): void
    {
        $spy = new BatchSpyHttpClient([
            ['content' => '{"x":1}', 'throwOnToArray' => new \RuntimeException('malformed json')],
        ]);
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $results = $adapter->sendMany([
            'bad' => new PreparedRequest(FakePathAction::create('GET', '/bad'), null, null),
        ]);

        $error = $results['bad'];
        self::assertInstanceOf(RequestResponseException::class, $error);
        self::assertSame(0, $error->statusCode);
        self::assertStringContainsString('Network error on GET /bad: malformed json', $error->getMessage());
    }

    #[Test]
    public function sendManyKeepsPathResolutionFailuresAsTheirOwnExceptionType(): void
    {
        $spy = new BatchSpyHttpClient([
            ['content' => '{"ok":true}'],
        ]);
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $results = $adapter->sendMany([
            'unresolved' => new PreparedRequest(FakePathAction::create('GET', '/orders/{id}'), null, null),
            'ok' => new PreparedRequest(FakePathAction::create('GET', '/healthy'), null, null),
        ]);

        self::assertInstanceOf(PathResolutionException::class, $results['unresolved']);
        self::assertSame(['ok' => true], $results['ok']);
    }

    #[Test]
    public function sendManyResolvesEachPathWithItsOwnContext(): void
    {
        $spy = new BatchSpyHttpClient([
            ['content' => '{"id":7}'],
            ['content' => '{"id":9}'],
        ]);
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $adapter->sendMany([
            'seven' => new PreparedRequest(FakePathAction::create('GET', '/orders/{id}'), FakeContext::create(['id' => '7']), null),
            'nine' => new PreparedRequest(FakePathAction::create('GET', '/orders/{id}'), FakeContext::create(['id' => '9']), null),
        ]);

        self::assertSame(
            ['request /orders/7', 'request /orders/9', 'consume /orders/7', 'consume /orders/9'],
            $spy->log,
        );
    }
}

// ── Fixtures ──────────────────────────────────────────────────────────────────

/**
 * Replays one plan per request() call and logs the order of dispatches and
 * consumptions, so tests can assert all requests go out before any response
 * is read.
 */
final class BatchSpyHttpClient implements HttpClientInterface
{
    /** @var list<string> */
    public array $log = [];

    /** @var list<string> */
    public array $urls = [];
    private int $next = 0;

    /** @param list<array{status?: int, content?: string, throwOnRequest?: \Throwable, throwOnToArray?: \Throwable}> $plans */
    public function __construct(private readonly array $plans) {}

    public function logConsume(string $path): void
    {
        $this->log[] = 'consume '.$path;
    }

    /** @param array<string, mixed> $options */
    public function request(string $method, string $url, array $options = []): HttpResponseInterface
    {
        $plan = $this->plans[$this->next++];
        $this->urls[] = $url;
        $path = parse_url($url, PHP_URL_PATH);
        $path = \is_string($path) ? $path : $url;
        $this->log[] = 'request '.$path;

        if (isset($plan['throwOnRequest'])) {
            throw $plan['throwOnRequest'];
        }

        return new BatchSpyResponse(
            client: $this,
            path: $path,
            statusCode: $plan['status'] ?? 200,
            content: $plan['content'] ?? '[]',
            throwOnToArray: $plan['throwOnToArray'] ?? null,
        );
    }

    public function stream(HttpResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        throw new \LogicException('Not implemented.');
    }

    /** @param array<string, mixed> $options */
    public function withOptions(array $options): static
    {
        return $this;
    }
}

final class BatchSpyResponse implements HttpResponseInterface
{
    private bool $consumed = false;

    public function __construct(
        private readonly BatchSpyHttpClient $client,
        private readonly string $path,
        private readonly int $statusCode,
        private readonly string $content,
        private readonly ?\Throwable $throwOnToArray,
    ) {}

    public function getStatusCode(): int
    {
        if (!$this->consumed) {
            $this->consumed = true;
            $this->client->logConsume($this->path);
        }

        return $this->statusCode;
    }

    /** @return array<mixed> */
    public function getHeaders(bool $throw = true): array
    {
        return [];
    }

    public function getContent(bool $throw = true): string
    {
        return $this->content;
    }

    /** @return array<mixed> */
    public function toArray(bool $throw = true): array
    {
        if (null !== $this->throwOnToArray) {
            throw $this->throwOnToArray;
        }

        $decoded = json_decode($this->content, true);

        return \is_array($decoded) ? $decoded : [];
    }

    public function cancel(): void
    { // No-op: cancellation not needed in test double
    }

    public function getInfo(?string $type = null): mixed
    {
        return null;
    }
}
