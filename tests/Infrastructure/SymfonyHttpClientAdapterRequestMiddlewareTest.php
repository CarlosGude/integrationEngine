<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionBodyInterface;
use IntegrationEngine\Core\Contract\Client\Request;
use IntegrationEngine\Core\Contract\Client\RequestMiddlewareInterface;
use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * request_middlewares run on the fully-built request (method, resolved
 * URL, headers, body) right before the HTTP call — the extension point
 * for request signing schemes like OAuth 1.0a that need the final request
 * to compute a signature.
 */
final class SymfonyHttpClientAdapterRequestMiddlewareTest extends TestCase
{
    #[Test]
    public function middlewareRunsBeforeTheHttpClientAndSeesTheResolvedRequest(): void
    {
        $spy = new RequestMiddlewareSpyHttpClient();
        $seen = null;
        $middleware = new RecordingRequestMiddleware(static function (Request $r) use (&$seen): void {
            $seen = $r;
        });
        $adapter = new SymfonyHttpClientAdapter(
            httpClient: $spy,
            baseUrl: 'https://api.example.com',
            requestMiddlewares: [$middleware],
        );

        $adapter->send(RequestMiddlewareTestAction::create('POST', '/orders', RequestMiddlewareTestBody::create(['id' => 1])));

        self::assertNotNull($seen);
        self::assertSame('POST', $seen->method);
        self::assertSame('https://api.example.com/orders', $seen->url);
        self::assertSame(['id' => 1], $seen->body);
        self::assertTrue($spy->wasCalled(), 'HTTP client must still be reached after the middleware runs.');
    }

    #[Test]
    public function middlewareCanAddAHeaderBeforeDispatch(): void
    {
        $spy = new RequestMiddlewareSpyHttpClient();
        $middleware = new AddHeaderRequestMiddleware('Authorization', 'OAuth signature="abc"');
        $adapter = new SymfonyHttpClientAdapter(
            httpClient: $spy,
            baseUrl: 'https://api.example.com',
            requestMiddlewares: [$middleware],
        );

        $adapter->send(RequestMiddlewareTestAction::create('GET', '/orders'));

        $headers = $spy->lastOptions()['headers'];
        self::assertIsArray($headers);
        self::assertSame('OAuth signature="abc"', $headers['Authorization']);
    }

    #[Test]
    public function middlewareCanReplaceTheRequestEntirely(): void
    {
        $spy = new RequestMiddlewareSpyHttpClient();
        $middleware = new ReplaceUrlRequestMiddleware('https://signed.example.com/orders?signed=1');
        $adapter = new SymfonyHttpClientAdapter(
            httpClient: $spy,
            baseUrl: 'https://api.example.com',
            requestMiddlewares: [$middleware],
        );

        $adapter->send(RequestMiddlewareTestAction::create('GET', '/orders'));

        self::assertSame('https://signed.example.com/orders?signed=1', $spy->lastUrl());
    }

    #[Test]
    public function chainContinuesCorrectlyThroughNext(): void
    {
        $spy = new RequestMiddlewareSpyHttpClient();
        $middleware = new AddHeaderRequestMiddleware('X-Pass-Through', 'yes');
        $adapter = new SymfonyHttpClientAdapter(
            httpClient: $spy,
            baseUrl: 'https://api.example.com',
            requestMiddlewares: [$middleware],
        );

        $result = $adapter->send(RequestMiddlewareTestAction::create('GET', '/orders'));

        self::assertSame(['id' => 1], $result['body']);
    }

    #[Test]
    public function multipleMiddlewaresRunInDeterministicDeclarationOrder(): void
    {
        $log = new RequestMiddlewareCallLog();
        $spy = new RequestMiddlewareSpyHttpClient();
        $adapter = new SymfonyHttpClientAdapter(
            httpClient: $spy,
            baseUrl: 'https://api.example.com',
            requestMiddlewares: [
                new OrderLoggingRequestMiddleware('A', $log),
                new OrderLoggingRequestMiddleware('B', $log),
            ],
        );

        $adapter->send(RequestMiddlewareTestAction::create('GET', '/orders'));

        self::assertSame(['A:before', 'B:before', 'B:after', 'A:after'], $log->all());
    }

    #[Test]
    public function responsePropagatesBackThroughEveryMiddleware(): void
    {
        $spy = new RequestMiddlewareSpyHttpClient();
        $adapter = new SymfonyHttpClientAdapter(
            httpClient: $spy,
            baseUrl: 'https://api.example.com',
            requestMiddlewares: [
                new ResponseObservingRequestMiddleware(),
                new ResponseObservingRequestMiddleware(),
            ],
        );

        $result = $adapter->send(RequestMiddlewareTestAction::create('GET', '/orders'));

        // Each of the two middlewares wraps the body with 'seen' => true on
        // its way back up, proving both observed the real response.
        self::assertTrue($result['body']['seen']);
        self::assertSame(2, $result['body']['wrap_count']);
    }

    #[Test]
    public function aMiddlewareCanRejectTheRequestWithoutCallingNext(): void
    {
        $spy = new RequestMiddlewareSpyHttpClient();
        $adapter = new SymfonyHttpClientAdapter(
            httpClient: $spy,
            baseUrl: 'https://api.example.com',
            requestMiddlewares: [new RejectingRequestMiddleware()],
        );

        $this->expectException(RequestResponseException::class);
        $this->expectExceptionMessage('rejected by test middleware');

        try {
            $adapter->send(RequestMiddlewareTestAction::create('GET', '/orders'));
        } finally {
            self::assertFalse($spy->wasCalled(), 'The HTTP client must never be reached when a middleware rejects.');
        }
    }

    #[Test]
    public function aMiddlewareCanShortCircuitWithACannedResponseWithoutCallingNext(): void
    {
        $spy = new RequestMiddlewareSpyHttpClient();
        $adapter = new SymfonyHttpClientAdapter(
            httpClient: $spy,
            baseUrl: 'https://api.example.com',
            requestMiddlewares: [new ShortCircuitRequestMiddleware()],
        );

        $result = $adapter->send(RequestMiddlewareTestAction::create('GET', '/orders'));

        self::assertSame(['cached' => true], $result['body']);
        self::assertFalse($spy->wasCalled());
    }

    #[Test]
    public function withBaseUrlPreservesConfiguredRequestMiddlewares(): void
    {
        $spy = new RequestMiddlewareSpyHttpClient();
        $middleware = new AddHeaderRequestMiddleware('X-Signed', 'yes');
        $adapter = new SymfonyHttpClientAdapter(
            httpClient: $spy,
            baseUrl: 'https://api.example.com',
            requestMiddlewares: [$middleware],
        );

        $resolved = $adapter->withBaseUrl('https://tenant.example.com');
        $resolved->send(RequestMiddlewareTestAction::create('GET', '/orders'));

        $headers = $spy->lastOptions()['headers'];
        self::assertIsArray($headers);
        self::assertSame('yes', $headers['X-Signed']);
    }

    #[Test]
    public function sendManyStillAppliesRequestMiddlewaresToEveryItem(): void
    {
        $spy = new RequestMiddlewareSpyHttpClient();
        $adapter = new SymfonyHttpClientAdapter(
            httpClient: $spy,
            baseUrl: 'https://api.example.com',
            requestMiddlewares: [new AddHeaderRequestMiddleware('X-Signed', 'yes')],
        );

        $results = $adapter->sendMany([
            'a' => new PreparedRequest(RequestMiddlewareTestAction::create('GET', '/orders'), null, null),
            'b' => new PreparedRequest(RequestMiddlewareTestAction::create('GET', '/orders'), null, null),
        ]);

        $headers = $spy->lastOptions()['headers'];
        self::assertIsArray($headers);
        self::assertSame('yes', $headers['X-Signed']);

        $resultA = $results['a'];
        self::assertIsArray($resultA);
        self::assertSame(['id' => 1], $resultA['body']);

        $resultB = $results['b'];
        self::assertIsArray($resultB);
        self::assertSame(['id' => 1], $resultB['body']);

        self::assertSame(2, $spy->callCount());
    }
}

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class RequestMiddlewareTestBody implements ActionBodyInterface
{
    /** @param array<string, mixed> $data */
    private function __construct(private readonly array $data) {}

    /** @param array<string, mixed> $data */
    public static function create(array $data): self
    {
        return new self($data);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }
}

final class RequestMiddlewareTestAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'request_middleware_test_action';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): ?string
    {
        return null;
    }
}

/** Calls a closure with the request it observed, then continues the chain. */
final class RecordingRequestMiddleware implements RequestMiddlewareInterface
{
    /** @param \Closure(Request): void $observer */
    public function __construct(private readonly \Closure $observer) {}

    public function handle(Request $request, callable $next): array
    {
        ($this->observer)($request);

        return $next($request);
    }
}

final class AddHeaderRequestMiddleware implements RequestMiddlewareInterface
{
    public function __construct(
        private readonly string $name,
        private readonly string $value,
    ) {}

    public function handle(Request $request, callable $next): array
    {
        return $next($request->withHeader($this->name, $this->value));
    }
}

final class ReplaceUrlRequestMiddleware implements RequestMiddlewareInterface
{
    public function __construct(private readonly string $url) {}

    public function handle(Request $request, callable $next): array
    {
        return $next(new Request($request->method, $this->url, $request->headers, $request->body));
    }
}

/** @param list<string> &$log */
/** Shared, appendable call log — passed as a plain object so several middleware instances can record into the same log. */
final class RequestMiddlewareCallLog
{
    /** @var list<string> */
    private array $entries = [];

    public function record(string $entry): void
    {
        $this->entries[] = $entry;
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->entries;
    }
}

final class OrderLoggingRequestMiddleware implements RequestMiddlewareInterface
{
    public function __construct(
        private readonly string $name,
        private readonly RequestMiddlewareCallLog $log,
    ) {}

    public function handle(Request $request, callable $next): array
    {
        $this->log->record("{$this->name}:before");
        $result = $next($request);
        $this->log->record("{$this->name}:after");

        return $result;
    }
}

/** Wraps the response body with a marker on the way back, proving it observed the real response. */
final class ResponseObservingRequestMiddleware implements RequestMiddlewareInterface
{
    public function handle(Request $request, callable $next): array
    {
        $result = $next($request);
        $result['body']['seen'] = true;
        $wrapCount = $result['body']['wrap_count'] ?? null;
        $result['body']['wrap_count'] = (\is_int($wrapCount) ? $wrapCount : 0) + 1;

        return $result;
    }
}

final class RejectingRequestMiddleware implements RequestMiddlewareInterface
{
    public function handle(Request $request, callable $next): array
    {
        throw new RequestResponseException(statusCode: 0, context: 'rejected by test middleware');
    }
}

final class ShortCircuitRequestMiddleware implements RequestMiddlewareInterface
{
    public function handle(Request $request, callable $next): array
    {
        return ['body' => ['cached' => true], 'headers' => []];
    }
}

final class RequestMiddlewareSpyHttpClient implements HttpClientInterface
{
    private bool $called = false;
    private int $callCount = 0;
    private string $lastUrl = '';

    /** @var array<string, mixed> */
    private array $lastOptions = [];

    public function wasCalled(): bool
    {
        return $this->called;
    }

    public function callCount(): int
    {
        return $this->callCount;
    }

    public function lastUrl(): string
    {
        return $this->lastUrl;
    }

    /** @return array<string, mixed> */
    public function lastOptions(): array
    {
        return $this->lastOptions;
    }

    /** @param array<string, mixed> $options */
    public function request(string $method, string $url, array $options = []): HttpResponseInterface
    {
        $this->called = true;
        ++$this->callCount;
        $this->lastUrl = $url;
        $this->lastOptions = $options;

        return new class implements HttpResponseInterface {
            public function getStatusCode(): int
            {
                return 200;
            }

            /** @return array<string, list<string>> */
            public function getHeaders(bool $throw = true): array
            {
                return [];
            }

            public function getContent(bool $throw = true): string
            {
                return '{"id":1}';
            }

            /** @return array<mixed> */
            public function toArray(bool $throw = true): array
            {
                return ['id' => 1];
            }

            public function cancel(): void
            { // No-op: cancellation not needed in test double
            }

            public function getInfo(?string $type = null): mixed
            {
                return null;
            }
        };
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
