<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\ActionContextInterface;
use IntegrationEngine\Core\Contract\RequestHeadersInterface;
use IntegrationEngine\Core\Contract\StaticAuthorizationConfig;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

final class SymfonyHttpClientAdapterHeadersTest extends TestCase
{
    #[Test]
    public function defaultHeadersFromYamlAreSent(): void
    {
        $httpClient = new SpyHttpClient();

        $adapter = new SymfonyHttpClientAdapter(
            httpClient: $httpClient,
            baseUrl: 'https://api.example.com',
            defaultHeaders: ['X-Api-Version' => '2', 'X-Client' => 'test'],
        );

        $adapter->send(HeadersTestAction::create('GET', '/test'));

        self::assertSame('2', $httpClient->lastOptions()['headers']['X-Api-Version']);
        self::assertSame('test', $httpClient->lastOptions()['headers']['X-Client']);
    }

    #[Test]
    public function authHeaderOverridesDefaultHeader(): void
    {
        $httpClient = new SpyHttpClient();

        $adapter = new SymfonyHttpClientAdapter(
            httpClient: $httpClient,
            baseUrl: 'https://api.example.com',
            defaultHeaders: ['Authorization' => 'Bearer old-token'],
        );

        $action = HeadersTestAction::create(
            method: 'GET',
            path: '/test',
            authorization: new StaticAuthorizationConfig(
                type: 'bearer',
                params: ['token' => 'new-token'],
            ),
        );

        $adapter->send($action);

        self::assertSame(
            'Bearer new-token',
            $httpClient->lastOptions()['headers']['Authorization']
        );
    }

    #[Test]
    public function callerHeaderOverridesAuthHeader(): void
    {
        $httpClient = new SpyHttpClient();

        $adapter = new SymfonyHttpClientAdapter(
            httpClient: $httpClient,
            baseUrl: 'https://api.example.com',
        );

        $action = HeadersTestAction::create(
            method: 'GET',
            path: '/test',
            authorization: new StaticAuthorizationConfig(
                type: 'bearer',
                params: ['token' => 'engine-token'],
            ),
        );

        $callerHeaders = new class implements RequestHeadersInterface {
            public function toArray(): array
            {
                return ['Authorization' => 'Bearer caller-token'];
            }
        };

        $adapter->send($action, null, $callerHeaders);

        self::assertSame(
            'Bearer caller-token',
            $httpClient->lastOptions()['headers']['Authorization']
        );
    }

    #[Test]
    public function noDefaultHeadersSendsOnlyAccept(): void
    {
        $httpClient = new SpyHttpClient();

        $adapter = new SymfonyHttpClientAdapter(
            httpClient: $httpClient,
            baseUrl: 'https://api.example.com',
        );

        $adapter->send(HeadersTestAction::create('GET', '/test'));

        self::assertSame(
            ['Accept' => 'application/json'],
            $httpClient->lastOptions()['headers']
        );
    }

    #[Test]
    public function allThreeLayersMergeInCorrectOrder(): void
    {
        $httpClient = new SpyHttpClient();

        $adapter = new SymfonyHttpClientAdapter(
            httpClient: $httpClient,
            baseUrl: 'https://api.example.com',
            defaultHeaders: [
                'X-Layer' => 'yaml',
                'X-Api-Version' => '1',
            ],
        );

        $action = HeadersTestAction::create(
            method: 'GET',
            path: '/test',
            authorization: new StaticAuthorizationConfig(
                type: 'api_key',
                params: ['header' => 'X-Layer', 'token' => 'auth'],
            ),
        );

        $callerHeaders = new class implements RequestHeadersInterface {
            public function toArray(): array
            {
                return ['X-Layer' => 'caller'];
            }
        };

        $adapter->send($action, null, $callerHeaders);

        // Caller overrides auth, auth overrides YAML
        self::assertSame('caller', $httpClient->lastOptions()['headers']['X-Layer']);
        // X-Api-Version comes from YAML, nobody overrides it
        self::assertSame('1', $httpClient->lastOptions()['headers']['X-Api-Version']);
    }

    #[Test]
    public function contextIsPassedToGetPath(): void
    {
        $httpClient = new SpyHttpClient();

        $adapter = new SymfonyHttpClientAdapter(
            httpClient: $httpClient,
            baseUrl: 'https://api.example.com',
        );

        $action = HeadersTestAction::create('GET', '/orders/{id}');

        $context = new class implements ActionContextInterface {
            public static function create(array $data): self
            {
                return new self();
            }

            public function toArray(): array
            {
                return ['id' => '42'];
            }
        };

        $adapter->send($action, $context);

        self::assertSame(
            'https://api.example.com/orders/42',
            $httpClient->lastUrl()
        );
    }
}

// ──────────────────────────────────────────────
// Inline fakes
// ──────────────────────────────────────────────

final class SpyHttpClient implements HttpClientInterface
{
    /** @var array<string, mixed> */
    private array $lastOptions = [];
    private string $lastUrl = '';

    /** @return array<string, mixed> */
    public function lastOptions(): array
    {
        return $this->lastOptions;
    }

    public function lastUrl(): string
    {
        return $this->lastUrl;
    }

    /** @param array<string, mixed> $options */
    public function request(string $method, string $url, array $options = []): HttpResponseInterface
    {
        $this->lastOptions = $options;
        $this->lastUrl = $url;

        return new class implements HttpResponseInterface {
            public function getStatusCode(): int
            {
                return 200;
            }

            /** @return array<string, array<int, string>> */
            public function getHeaders(bool $throw = true): array
            {
                return [];
            }

            public function getContent(bool $throw = true): string
            {
                return '[]';
            }

            /** @return array<mixed> */
            public function toArray(bool $throw = true): array
            {
                return [];
            }

            public function cancel(): void {}

            public function getInfo(?string $type = null): mixed
            {
                return null;
            }
        };
    }

    public function stream(HttpResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        throw new \LogicException('Not implemented in test spy.');
    }

    /** @param array<string, mixed> $options */
    public function withOptions(array $options): static
    {
        return $this;
    }
}

final class HeadersTestAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'headers_test';
    }

    public static function hasResponse(): bool
    {
        return false;
    }

    public static function mapper(): ?string
    {
        return null;
    }
}
