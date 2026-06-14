<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\GraphQLBodyInterface;
use IntegrationEngine\Core\Contract\RequestHeadersInterface;
use IntegrationEngine\Core\Contract\StaticAuthorizationConfig;
use IntegrationEngine\Infrastructure\Http\GraphQLClientAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Tests for GraphQLClientAdapter::resolveHeaders — Bloque D.
 * Kills MatchArmRemoval, LogicalAnd, Assignment (+=), Coalesce mutants.
 */
final class GraphQLClientAdapterHeadersTest extends TestCase
{
    // ── bearer ────────────────────────────────────────────────────────────────

    #[Test]
    public function bearerAuthSetsBearerAuthorizationHeader(): void
    {
        $spy = new GQLHeadersSpyClient();
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $action = GQLHeadersAction::create(
            method: 'POST',
            path: '/graphql',
            body: GQLHeadersBody::create([]),
            authorization: new StaticAuthorizationConfig(
                type: 'bearer',
                params: ['token' => 'my-token'],
            ),
        );

        $adapter->send($action);

        self::assertSame('Bearer my-token', $spy->lastHeaders()['Authorization']);
    }

    #[Test]
    public function bearerAuthUsesCustomPrefix(): void
    {
        $spy = new GQLHeadersSpyClient();
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $action = GQLHeadersAction::create(
            method: 'POST',
            path: '/graphql',
            body: GQLHeadersBody::create([]),
            authorization: new StaticAuthorizationConfig(
                type: 'bearer',
                params: ['token' => 'my-token', 'prefix' => 'Token'],
            ),
        );

        $adapter->send($action);

        self::assertSame('Token my-token', $spy->lastHeaders()['Authorization']);
    }

    #[Test]
    public function bearerAuthFallsBackToBearerPrefixWhenMissing(): void
    {
        // Kills Coalesce mutant — prefix ?? 'Bearer' must use 'Bearer' when prefix absent
        $spy = new GQLHeadersSpyClient();
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $action = GQLHeadersAction::create(
            method: 'POST',
            path: '/graphql',
            body: GQLHeadersBody::create([]),
            authorization: new StaticAuthorizationConfig(
                type: 'bearer',
                params: ['token' => 'abc'],
                // no prefix key
            ),
        );

        $adapter->send($action);

        self::assertStringStartsWith('Bearer ', $spy->lastHeaders()['Authorization']);
    }

    // ── basic ─────────────────────────────────────────────────────────────────

    #[Test]
    public function basicAuthSetsBase64EncodedAuthorizationHeader(): void
    {
        // Kills MatchArmRemoval for 'basic' arm
        $spy = new GQLHeadersSpyClient();
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $action = GQLHeadersAction::create(
            method: 'POST',
            path: '/graphql',
            body: GQLHeadersBody::create([]),
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
        $spy = new GQLHeadersSpyClient();
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $action = GQLHeadersAction::create(
            method: 'POST',
            path: '/graphql',
            body: GQLHeadersBody::create([]),
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
        $spy = new GQLHeadersSpyClient();
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $action = GQLHeadersAction::create(
            method: 'POST',
            path: '/graphql',
            body: GQLHeadersBody::create([]),
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
        $spy = new GQLHeadersSpyClient();
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $action = GQLHeadersAction::create(
            method: 'POST',
            path: '/graphql',
            body: GQLHeadersBody::create([]),
            authorization: new StaticAuthorizationConfig(
                type: 'api_key',
                params: ['token' => 'secret'],
                // no 'header' key
            ),
        );

        $adapter->send($action);

        self::assertSame('secret', $spy->lastHeaders()['X-Api-Key']);
    }

    // ── default ───────────────────────────────────────────────────────────────

    #[Test]
    public function unknownAuthTypeThrows(): void
    {
        // Kills MatchArmRemoval for 'default' arm.
        // Options are built before the transport try-block so the
        // InvalidArgumentException propagates directly as a config error, not
        // wrapped as a network failure.
        $spy = new GQLHeadersSpyClient();
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $action = GQLHeadersAction::create(
            method: 'POST',
            path: '/graphql',
            body: GQLHeadersBody::create([]),
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
        // defaultHeaders includes Content-Type; bearer auth adds Authorization.
        // With +=, both survive. With =, only Authorization remains.
        $spy = new GQLHeadersSpyClient();
        $adapter = new GraphQLClientAdapter(
            httpClient: $spy,
            endpointUrl: 'https://api.example.com/graphql',
            defaultHeaders: ['X-Custom' => 'preserved'],
        );

        $action = GQLHeadersAction::create(
            method: 'POST',
            path: '/graphql',
            body: GQLHeadersBody::create([]),
            authorization: new StaticAuthorizationConfig(
                type: 'bearer',
                params: ['token' => 'tok'],
            ),
        );

        $adapter->send($action);

        self::assertArrayHasKey('Authorization', $spy->lastHeaders());
        self::assertSame('preserved', $spy->lastHeaders()['X-Custom']);
    }

    // ── caller header overrides engine auth ───────────────────────────────────

    #[Test]
    public function callerHeaderOverridesAuthHeader(): void
    {
        $spy = new GQLHeadersSpyClient();
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $action = GQLHeadersAction::create(
            method: 'POST',
            path: '/graphql',
            body: GQLHeadersBody::create([]),
            authorization: new StaticAuthorizationConfig(type: 'bearer', params: ['token' => 'engine-token']),
        );

        $callerHeaders = new class implements RequestHeadersInterface {
            public function toArray(): array
            {
                return ['Authorization' => 'Bearer caller-token'];
            }
        };

        $adapter->send($action, null, $callerHeaders);

        self::assertSame('Bearer caller-token', $spy->lastHeaders()['Authorization']);
    }

    // ── no auth — returns headers unchanged ───────────────────────────────────

    #[Test]
    public function noAuthReturnsEmptyHeadersFromResolveHeaders(): void
    {
        // Kills ArrayOneItem mutant on the early return when no StaticAuthorizationConfig
        $spy = new GQLHeadersSpyClient();
        $adapter = new GraphQLClientAdapter(
            httpClient: $spy,
            endpointUrl: 'https://api.example.com/graphql',
            defaultHeaders: ['X-A' => '1', 'X-B' => '2'],
        );

        // Action with no auth — resolveHeaders returns [] and defaultHeaders survive
        $adapter->send(GQLHeadersAction::create('POST', '/graphql', GQLHeadersBody::create([])));

        self::assertSame('1', $spy->lastHeaders()['X-A']);
        self::assertSame('2', $spy->lastHeaders()['X-B']);
        self::assertArrayNotHasKey('Authorization', $spy->lastHeaders());
    }
}

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class GQLHeadersSpyClient implements HttpClientInterface
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
                return '{"data":[]}';
            }

            /** @return array<mixed> */
            public function toArray(bool $throw = true): array
            {
                return ['data' => []];
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

final class GQLHeadersBody implements GraphQLBodyInterface
{
    private function __construct()
    { // Intentionally empty: use factory method
    }

    /** @param array<string, mixed> $data */
    public static function create(array $data): self
    {
        return new self();
    }

    public function getQuery(): string
    {
        return 'query { items { id } }';
    }

    /** @return array<string, mixed> */
    public function getVariables(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['query' => $this->getQuery(), 'variables' => []];
    }
}

final class GQLHeadersAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'gql_headers_test';
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
