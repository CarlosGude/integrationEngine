<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * send() must return {body, headers} — the body preserved exactly as
 * before, plus the response's HTTP headers alongside it, without any
 * provider-specific adapter needed to obtain them.
 */
final class SymfonyHttpClientAdapterResponseHeadersTest extends TestCase
{
    #[Test]
    public function bodyIsPreservedAlongsideHeaders(): void
    {
        $spy = new ResponseHeadersSpyHttpClient(content: '{"id":1}', headers: ['X-Request-Id' => ['abc123']]);
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $result = $adapter->send(ResponseHeadersTestAction::create('GET', '/orders'));

        self::assertSame(['id' => 1], $result['body']);
    }

    #[Test]
    public function responseHeadersArePropagated(): void
    {
        $spy = new ResponseHeadersSpyHttpClient(
            content: '{}',
            headers: ['X-Request-Id' => ['abc123'], 'X-RateLimit-Remaining' => ['42']],
        );
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $result = $adapter->send(ResponseHeadersTestAction::create('GET', '/orders'));

        self::assertSame(
            ['X-Request-Id' => ['abc123'], 'X-RateLimit-Remaining' => ['42']],
            $result['headers'],
        );
    }

    #[Test]
    public function multipleValuesForTheSameHeaderArePreserved(): void
    {
        $spy = new ResponseHeadersSpyHttpClient(content: '{}', headers: ['Set-Cookie' => ['a=1', 'b=2']]);
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $result = $adapter->send(ResponseHeadersTestAction::create('GET', '/orders'));

        self::assertSame(['a=1', 'b=2'], $result['headers']['Set-Cookie']);
    }

    #[Test]
    public function absentHeadersResolveToAnEmptyArray(): void
    {
        $spy = new ResponseHeadersSpyHttpClient(content: '{}', headers: []);
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $result = $adapter->send(ResponseHeadersTestAction::create('GET', '/orders'));

        self::assertSame([], $result['headers']);
    }

    /**
     * Regression: the successful path must read response headers without
     * throwing on a non-2xx-shaped header set — getHeaders(throw: false)
     * is required here, not throw: true.
     */
    #[Test]
    public function responseHeadersAreReadWithoutThrowing(): void
    {
        $spy = new ResponseHeadersSpyHttpClient(content: '{"id":1}', headers: [], throwOnGetHeadersTrue: true);
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $result = $adapter->send(ResponseHeadersTestAction::create('GET', '/orders'));

        self::assertSame(['id' => 1], $result['body']);
    }

    #[Test]
    public function sendManyAlsoPropagatesHeadersPerRequest(): void
    {
        $spy = new ResponseHeadersSpyHttpClient(content: '{}', headers: ['X-Trace-Id' => ['batch-1']]);
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $results = $adapter->sendMany([
            'a' => new PreparedRequest(ResponseHeadersTestAction::create('GET', '/orders'), null, null),
        ]);

        $resultA = $results['a'];
        self::assertIsArray($resultA);
        self::assertSame(['X-Trace-Id' => ['batch-1']], $resultA['headers']);
    }
}

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class ResponseHeadersTestAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'response_headers_test_action';
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

final class ResponseHeadersSpyHttpClient implements HttpClientInterface
{
    /** @param array<string, list<string>> $headers */
    public function __construct(
        private readonly string $content,
        private readonly array $headers,
        private readonly bool $throwOnGetHeadersTrue = false,
    ) {}

    /** @param array<string, mixed> $options */
    public function request(string $method, string $url, array $options = []): HttpResponseInterface
    {
        $content = $this->content;
        $headers = $this->headers;
        $throwOnGetHeadersTrue = $this->throwOnGetHeadersTrue;

        return new class($content, $headers, $throwOnGetHeadersTrue) implements HttpResponseInterface {
            /** @param array<string, list<string>> $headers */
            public function __construct(
                private readonly string $content,
                private readonly array $headers,
                private readonly bool $throwOnGetHeadersTrue,
            ) {}

            public function getStatusCode(): int
            {
                return 200;
            }

            /** @return array<string, list<string>> */
            public function getHeaders(bool $throw = true): array
            {
                if ($throw && $this->throwOnGetHeadersTrue) {
                    throw new \LogicException('getHeaders called with throw: true');
                }

                return $this->headers;
            }

            public function getContent(bool $throw = true): string
            {
                return $this->content;
            }

            /** @return array<mixed> */
            public function toArray(bool $throw = true): array
            {
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
