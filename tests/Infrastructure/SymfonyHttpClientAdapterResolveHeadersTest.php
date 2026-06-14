<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Auth\StaticAuthorizationConfig;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Tests for SymfonyHttpClientAdapter::resolveHeaders.
 * Kills MatchArmRemoval (basic, api_key, default), LogicalAnd,
 * LogicalAndSingleSubExprNegation, Assignment (+=) and ArrayOneItem mutants.
 */
final class SymfonyHttpClientAdapterResolveHeadersTest extends TestCase
{
    // ── basic ─────────────────────────────────────────────────────────────────

    #[Test]
    public function basicAuthSetsBase64EncodedAuthorizationHeader(): void
    {
        $spy = new RestHeadersSpyClient();
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $action = RestHeadersAction::create(
            method: 'GET',
            path: '/orders',
            authorization: new StaticAuthorizationConfig(
                type: 'basic',
                params: ['username' => 'user', 'password' => 'pass'],
            ),
        );

        $adapter->send($action);

        $expected = 'Basic '.base64_encode('user:pass');
        self::assertSame($expected, $spy->lastHeaders()['Authorization']);
    }

    #[Test]
    public function basicAuthWithEmptyCredentialsEncodesEmptyString(): void
    {
        // Kills LogicalAndSingleSubExprNegation for username and password guards
        $spy = new RestHeadersSpyClient();
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $action = RestHeadersAction::create(
            method: 'GET',
            path: '/orders',
            authorization: new StaticAuthorizationConfig(
                type: 'basic',
                params: [],
            ),
        );

        $adapter->send($action);

        $expected = 'Basic '.base64_encode(':');
        self::assertSame($expected, $spy->lastHeaders()['Authorization']);
    }

    // ── api_key ───────────────────────────────────────────────────────────────

    #[Test]
    public function apiKeyAuthSetsCustomHeader(): void
    {
        // Kills MatchArmRemoval for 'api_key' arm
        $spy = new RestHeadersSpyClient();
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $action = RestHeadersAction::create(
            method: 'GET',
            path: '/orders',
            authorization: new StaticAuthorizationConfig(
                type: 'api_key',
                params: ['header' => 'X-My-Token', 'token' => 'secret'],
            ),
        );

        $adapter->send($action);

        self::assertSame('secret', $spy->lastHeaders()['X-My-Token']);
        self::assertArrayNotHasKey('Authorization', $spy->lastHeaders());
    }

    #[Test]
    public function apiKeyAuthFallsBackToXApiKeyWhenHeaderMissing(): void
    {
        // Kills LogicalAndSingleSubExprNegation for headerKey guard
        $spy = new RestHeadersSpyClient();
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $action = RestHeadersAction::create(
            method: 'GET',
            path: '/orders',
            authorization: new StaticAuthorizationConfig(
                type: 'api_key',
                params: ['token' => 'secret'],
            ),
        );

        $adapter->send($action);

        self::assertSame('secret', $spy->lastHeaders()['X-Api-Key']);
    }

    #[Test]
    public function apiKeyAuthWithPrefixPrependsItToTheToken(): void
    {
        // Kills LogicalAnd and Ternary mutants in the api_key prefix guard
        $spy = new RestHeadersSpyClient();
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $action = RestHeadersAction::create(
            method: 'GET',
            path: '/orders',
            authorization: new StaticAuthorizationConfig(
                type: 'api_key',
                params: ['header' => 'X-My-Token', 'token' => 'secret', 'prefix' => 'Token'],
            ),
        );

        $adapter->send($action);

        self::assertSame('Token secret', $spy->lastHeaders()['X-My-Token']);
    }

    #[Test]
    public function apiKeyAuthWithEmptyPrefixSendsBareToken(): void
    {
        // Kills the '' !== $prefix sub-expression mutant — an empty prefix
        // must not produce a token with a leading space.
        $spy = new RestHeadersSpyClient();
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $action = RestHeadersAction::create(
            method: 'GET',
            path: '/orders',
            authorization: new StaticAuthorizationConfig(
                type: 'api_key',
                params: ['header' => 'X-My-Token', 'token' => 'secret', 'prefix' => ''],
            ),
        );

        $adapter->send($action);

        self::assertSame('secret', $spy->lastHeaders()['X-My-Token']);
    }

    // ── default ───────────────────────────────────────────────────────────────

    #[Test]
    public function unknownAuthTypeThrows(): void
    {
        // Kills MatchArmRemoval for 'default' arm.
        // buildOptions() is called before the transport try-block so the
        // InvalidArgumentException propagates directly as a config error, not
        // wrapped as a network failure.
        $spy = new RestHeadersSpyClient();
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $action = RestHeadersAction::create(
            method: 'GET',
            path: '/orders',
            authorization: new StaticAuthorizationConfig(
                type: 'unknown_type',
                params: [],
            ),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown static authorization type "unknown_type"/');

        $adapter->send($action);
    }

    // ── += preserves existing headers ─────────────────────────────────────────

    #[Test]
    public function defaultHeadersArePreservedWhenAuthHeaderIsAdded(): void
    {
        // Kills Assignment mutant: $headers += vs $headers =
        // resolveHeaders starts with ['Accept' => 'application/json']
        // bearer auth adds Authorization via +=
        // With =, Accept would be lost
        $spy = new RestHeadersSpyClient();
        $adapter = new SymfonyHttpClientAdapter(
            httpClient: $spy,
            baseUrl: 'https://api.example.com',
        );

        $action = RestHeadersAction::create(
            method: 'GET',
            path: '/orders',
            authorization: new StaticAuthorizationConfig(
                type: 'bearer',
                params: ['token' => 'tok'],
            ),
        );

        $adapter->send($action);

        self::assertArrayHasKey('Authorization', $spy->lastHeaders());
        self::assertSame('application/json', $spy->lastHeaders()['Accept']);
    }

    // ── no auth — ArrayOneItem early return ───────────────────────────────────

    #[Test]
    public function noAuthReturnsOnlyAcceptHeader(): void
    {
        // Kills ArrayOneItem on early return when no StaticAuthorizationConfig
        // resolveHeaders returns ['Accept' => 'application/json'] — exactly 1 key
        // ArrayOneItem mutant would slice to 0 items if count > 1, but since
        // it's exactly 1 it returns as-is — this test verifies Accept survives
        $spy = new RestHeadersSpyClient();
        $adapter = new SymfonyHttpClientAdapter(
            httpClient: $spy,
            baseUrl: 'https://api.example.com',
            defaultHeaders: ['X-Custom' => 'value'],
        );

        $adapter->send(RestHeadersAction::create('GET', '/orders'));

        self::assertSame('application/json', $spy->lastHeaders()['Accept']);
        self::assertSame('value', $spy->lastHeaders()['X-Custom']);
        self::assertArrayNotHasKey('Authorization', $spy->lastHeaders());
    }

    // ── LogicalAnd token guard ────────────────────────────────────────────────

    #[Test]
    public function bearerWithMissingTokenParamUsesEmptyString(): void
    {
        // Kills LogicalAnd mutant on token isset && is_string guard
        $spy = new RestHeadersSpyClient();
        $adapter = new SymfonyHttpClientAdapter(httpClient: $spy, baseUrl: 'https://api.example.com');

        $action = RestHeadersAction::create(
            method: 'GET',
            path: '/orders',
            authorization: new StaticAuthorizationConfig(
                type: 'bearer',
                params: [],  // no 'token' key
            ),
        );

        $adapter->send($action);

        self::assertSame('Bearer ', $spy->lastHeaders()['Authorization']);
    }
}

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class RestHeadersSpyClient implements HttpClientInterface
{
    /** @var array<string, string> */
    private array $lastHeaders = [];

    /** @return array<string, string> */
    public function lastHeaders(): array
    {
        return $this->lastHeaders;
    }

    /** @param array<string, mixed> $options */
    public function request(string $method, string $url, array $options = []): HttpResponseInterface
    {
        $this->lastHeaders = [];
        $rawHeaders = $options['headers'] ?? null;
        if (\is_array($rawHeaders)) {
            foreach ($rawHeaders as $k => $v) {
                if (\is_string($k) && \is_string($v)) {
                    $this->lastHeaders[$k] = $v;
                }
            }
        }

        return new class implements HttpResponseInterface {
            public function getStatusCode(): int
            {
                return 200;
            }

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

final class RestHeadersAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'rest_headers_test';
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
