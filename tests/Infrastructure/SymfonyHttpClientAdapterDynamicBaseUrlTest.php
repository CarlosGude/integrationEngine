<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use IntegrationEngine\Tests\Fake\FakePathAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

final class SymfonyHttpClientAdapterDynamicBaseUrlTest extends TestCase
{
    #[Test]
    public function withBaseUrlReturnsANewInstanceWithoutMutatingTheOriginal(): void
    {
        $spy = new DynBaseUrlSpyHttpClient();
        $original = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://original.example.com');

        $resolved = $original->withBaseUrl('https://tenant-one.example.com');

        self::assertNotSame($original, $resolved);

        $original->send(FakePathAction::create('GET', '/items'));
        self::assertSame('https://original.example.com/items', $spy->lastUrl());
    }

    #[Test]
    public function requestAfterWithBaseUrlHitsTheNewUrlNotTheOriginal(): void
    {
        $spy = new DynBaseUrlSpyHttpClient();
        $original = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://original.example.com');

        $resolved = $original->withBaseUrl('https://tenant-one.example.com');
        $resolved->send(FakePathAction::create('GET', '/items'));

        self::assertSame('https://tenant-one.example.com/items', $spy->lastUrl());
    }

    #[Test]
    public function withBaseUrlPreservesDefaultHeaders(): void
    {
        $spy = new DynBaseUrlSpyHttpClient();
        $original = new SymfonyHttpClientAdapter(
            httpClient: $spy,
            baseUrl: 'https://original.example.com',
            defaultHeaders: ['X-Api-Version' => '2'],
        );

        $resolved = $original->withBaseUrl('https://tenant-one.example.com');
        $resolved->send(FakePathAction::create('GET', '/items'));

        self::assertSame('2', $spy->lastHeader('X-Api-Version'));
    }
}

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class DynBaseUrlSpyHttpClient implements HttpClientInterface
{
    private string $lastUrl = '';

    /** @var array<string, mixed> */
    private array $lastOptions = [];

    public function lastUrl(): string
    {
        return $this->lastUrl;
    }

    /** @return array<string, mixed> */
    public function lastOptions(): array
    {
        return $this->lastOptions;
    }

    public function lastHeader(string $name): ?string
    {
        $headers = $this->lastOptions['headers'] ?? null;

        return \is_array($headers) && \is_string($headers[$name] ?? null) ? $headers[$name] : null;
    }

    /** @param array<string, mixed> $options */
    public function request(string $method, string $url, array $options = []): HttpResponseInterface
    {
        $this->lastUrl = $url;
        $this->lastOptions = $options;

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

            public function cancel(): void
            { // Not implemented — test spy does not need to cancel requests
            }

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
