<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionBodyInterface;
use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

final class SymfonyHttpClientAdapterBodyTest extends TestCase
{
    // ── body serialisation ────────────────────────────────────────────────────

    #[Test]
    public function postWithBodySerializesJsonOption(): void
    {
        $spy = new BodySpyHttpClient();
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $adapter->send(BodyTestAction::create('POST', '/orders', BodyTestBody::create([])));

        self::assertArrayHasKey('json', $spy->lastOptions());
        self::assertSame(['reference' => 'ORD-001'], $spy->lastOptions()['json']);
    }

    #[Test]
    public function putWithBodySerializesJsonOption(): void
    {
        $spy = new BodySpyHttpClient();
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $adapter->send(BodyTestAction::create('PUT', '/orders/1', BodyTestBody::create([])));

        self::assertArrayHasKey('json', $spy->lastOptions());
    }

    #[Test]
    public function patchWithBodySerializesJsonOption(): void
    {
        $spy = new BodySpyHttpClient();
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $adapter->send(BodyTestAction::create('PATCH', '/orders/1', BodyTestBody::create([])));

        self::assertArrayHasKey('json', $spy->lastOptions());
    }

    #[Test]
    public function getWithBodyDoesNotSerializeJsonOption(): void
    {
        $spy = new BodySpyHttpClient();
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $adapter->send(BodyTestAction::create('GET', '/orders', BodyTestBody::create([])));

        self::assertArrayNotHasKey('json', $spy->lastOptions());
    }

    #[Test]
    public function postWithNullBodyDoesNotSerializeJsonOption(): void
    {
        $spy = new BodySpyHttpClient();
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $adapter->send(BodyTestAction::create('POST', '/orders'));

        self::assertArrayNotHasKey('json', $spy->lastOptions());
    }

    // ── >= 400 boundary ───────────────────────────────────────────────────────

    #[Test]
    public function statusCode400ThrowsRequestResponseException(): void
    {
        $spy = new BodySpyHttpClient(statusCode: 400);
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $this->expectException(RequestResponseException::class);

        $adapter->send(BodyTestAction::create('GET', '/orders'));
    }

    #[Test]
    public function http4xxStatusCodeIsPreservedWhenGetContentUsesThrowFalse(): void
    {
        $spy = new BodySpyHttpClient(statusCode: 400, throwOnGetContentTrue: true);
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        try {
            $adapter->send(BodyTestAction::create('GET', '/orders'));
            self::fail('Expected RequestResponseException');
        } catch (RequestResponseException $e) {
            self::assertSame(400, $e->statusCode);
        }
    }

    #[Test]
    public function statusCode399DoesNotThrow(): void
    {
        $spy = new BodySpyHttpClient(statusCode: 399, content: '{"ok":true}');
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $result = $adapter->send(BodyTestAction::create('GET', '/orders'));

        self::assertSame(['ok' => true], $result);
    }

    // ── \Throwable network error ──────────────────────────────────────────────

    #[Test]
    public function networkErrorIsWrappedInRequestResponseException(): void
    {
        $spy = new BodySpyHttpClient(throwOnRequest: new \RuntimeException('Connection refused'));
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        try {
            $adapter->send(BodyTestAction::create('GET', '/orders'));
            self::fail('Expected RequestResponseException');
        } catch (RequestResponseException $e) {
            self::assertSame(0, $e->statusCode);
            self::assertStringContainsString('Connection refused', $e->context);
        }
    }

    #[Test]
    public function requestResponseExceptionIsRethrownDirectly(): void
    {
        $original = new RequestResponseException(statusCode: 503, context: 'service unavailable');
        $spy = new BodySpyHttpClient(throwOnRequest: $original);
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        try {
            $adapter->send(BodyTestAction::create('GET', '/orders'));
            self::fail('Expected RequestResponseException');
        } catch (RequestResponseException $e) {
            self::assertSame($original, $e);
            self::assertSame(503, $e->statusCode);
        }
    }

    // ── 204 and empty body ────────────────────────────────────────────────────

    #[Test]
    public function statusCode204ReturnsEmptyArray(): void
    {
        $spy = new BodySpyHttpClient(statusCode: 204, content: '');
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $result = $adapter->send(BodyTestAction::create('DELETE', '/orders/1'));

        self::assertSame([], $result);
    }

    #[Test]
    public function statusCode204WithNonEmptyBodyReturnsEmptyArray(): void
    {
        $spy = new BodySpyHttpClient(statusCode: 204, content: '{"id":1}', overrideToArray: ['id' => 1]);
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $result = $adapter->send(BodyTestAction::create('DELETE', '/orders/1'));

        self::assertSame([], $result);
    }

    #[Test]
    public function whitespaceOnlyContentReturnsEmptyArray(): void
    {
        $spy = new BodySpyHttpClient(statusCode: 200, content: '   ', overrideToArray: ['unexpected' => 'data']);
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $result = $adapter->send(BodyTestAction::create('GET', '/orders'));

        self::assertSame([], $result);
    }

    #[Test]
    public function emptyBodyReturnsEmptyArray(): void
    {
        $spy = new BodySpyHttpClient(statusCode: 200, content: '');
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $result = $adapter->send(BodyTestAction::create('GET', '/orders'));

        self::assertSame([], $result);
    }

    #[Test]
    public function nonErrorContentIsReadWithThrowFalse(): void
    {
        $spy = new BodySpyHttpClient(statusCode: 200, content: '{"data":"ok"}', throwOnGetContentTrue: true);
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $result = $adapter->send(BodyTestAction::create('GET', '/orders'));

        self::assertSame(['data' => 'ok'], $result);
    }
}

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class BodyTestBody implements ActionBodyInterface
{
    private function __construct(/** @var array<string, mixed> */ private readonly array $data = ['reference' => 'ORD-001']) {}

    /** @param array<string, mixed> $data */
    public static function create(array $data = []): self
    {
        return new self();
    }

    public function toArray(): array
    {
        return $this->data;
    }
}

final class BodyTestAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'body_test_action';
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

final class BodySpyHttpClient implements HttpClientInterface
{
    /** @var array<string, mixed> */
    private array $lastOptions = [];

    /** @param null|array<mixed> $overrideToArray */
    public function __construct(
        private readonly int $statusCode = 200,
        private readonly string $content = '[]',
        private readonly ?\Throwable $throwOnRequest = null,
        private readonly bool $throwOnGetContentTrue = false,
        private readonly ?array $overrideToArray = null,
    ) {}

    /** @return array<string, mixed> */
    public function lastOptions(): array
    {
        return $this->lastOptions;
    }

    /** @param array<string, mixed> $options */
    public function request(string $method, string $url, array $options = []): HttpResponseInterface
    {
        $this->lastOptions = $options;

        if (null !== $this->throwOnRequest) {
            throw $this->throwOnRequest;
        }

        $statusCode = $this->statusCode;
        $content = $this->content;
        $throwOnGetContentTrue = $this->throwOnGetContentTrue;
        $overrideToArray = $this->overrideToArray;

        return new class($statusCode, $content, $throwOnGetContentTrue, $overrideToArray) implements HttpResponseInterface {
            /** @param null|array<mixed> $overrideToArray */
            public function __construct(
                private readonly int $statusCode,
                private readonly string $content,
                private readonly bool $throwOnGetContentTrue,
                private readonly ?array $overrideToArray,
            ) {}

            public function getStatusCode(): int
            {
                return $this->statusCode;
            }

            public function getHeaders(bool $throw = true): array
            {
                return [];
            }

            public function getContent(bool $throw = true): string
            {
                if ($throw && $this->throwOnGetContentTrue) {
                    throw new \LogicException('getContent called with throw: true');
                }

                return $this->content;
            }

            /** @return array<mixed> */
            public function toArray(bool $throw = true): array
            {
                if (null !== $this->overrideToArray) {
                    return $this->overrideToArray;
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
