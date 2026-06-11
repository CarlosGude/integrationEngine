<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\ActionBodyInterface;
use IntegrationEngine\Core\Contract\GraphQLBodyInterface;
use IntegrationEngine\Core\Contract\RequestHeadersInterface;
use IntegrationEngine\Core\Contract\StaticAuthorizationConfig;
use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Infrastructure\Http\GraphQLClientAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

final class GraphQLClientAdapterTest extends TestCase
{
    // ── Body serialisation ────────────────────────────────────────────────────

    #[Test]
    public function graphQLBodyIsSerialisedAsQueryAndVariables(): void
    {
        $spy = new GQLSpyHttpClient();
        $adapter = new GraphQLClientAdapter(
            httpClient: $spy,
            endpointUrl: 'https://api.example.com/graphql',
        );

        $adapter->send(GQLTestAction::create('POST', '/graphql', GQLTestBody::create([])));

        self::assertSame('POST', $spy->lastMethod());
        self::assertSame('https://api.example.com/graphql', $spy->lastUrl());
        /** @var array<string, mixed> $json */
        $json = $spy->lastOptions()['json'];
        self::assertSame('query { user { id } }', $json['query']);
        self::assertSame(['login' => 'test'], $json['variables']);
    }

    #[Test]
    public function alwaysPostsToEndpointUrlIgnoringActionPath(): void
    {
        $spy = new GQLSpyHttpClient();
        $adapter = new GraphQLClientAdapter(
            httpClient: $spy,
            endpointUrl: 'https://api.example.com/graphql',
        );

        // Action path is irrelevant for GraphQL — adapter always uses endpointUrl
        $adapter->send(GQLTestAction::create('GET', '/some/other/path', GQLTestBody::create([])));

        self::assertSame('https://api.example.com/graphql', $spy->lastUrl());
        self::assertSame('POST', $spy->lastMethod());
    }

    // ── data extraction ───────────────────────────────────────────────────────

    #[Test]
    public function graphQLDataIsExtractedBeforeMapper(): void
    {
        $spy = new GQLSpyHttpClient(responseBody: ['data' => ['user' => ['id' => '1', 'name' => 'Carlos']]]);
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $result = $adapter->send(GQLTestAction::create('POST', '/graphql', GQLTestBody::create([])));

        // Mapper receives only data[], not the full GraphQL envelope
        self::assertSame(['user' => ['id' => '1', 'name' => 'Carlos']], $result);
    }

    #[Test]
    public function nullDataKeyReturnsEmptyArray(): void
    {
        $spy = new GQLSpyHttpClient(responseBody: ['data' => null]);
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $result = $adapter->send(GQLTestAction::create('POST', '/graphql', GQLTestBody::create([])));

        self::assertSame([], $result);
    }

    // ── Error handling ────────────────────────────────────────────────────────

    #[Test]
    public function graphQLErrorsInResponseThrowsRequestResponseException(): void
    {
        $spy = new GQLSpyHttpClient(responseBody: [
            'errors' => [['message' => 'Field "user" not found']],
            'data' => null,
        ]);
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $this->expectException(RequestResponseException::class);
        $this->expectExceptionMessageMatches('/Field "user" not found/');

        $adapter->send(GQLTestAction::create('POST', '/graphql', GQLTestBody::create([])));
    }

    #[Test]
    public function graphQLErrorWithoutMessageThrowsGenericError(): void
    {
        $spy = new GQLSpyHttpClient(responseBody: [
            'errors' => [['extensions' => ['code' => 'UNAUTHENTICATED']]],
        ]);
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $this->expectException(RequestResponseException::class);
        $this->expectExceptionMessageMatches('/GraphQL error/');

        $adapter->send(GQLTestAction::create('POST', '/graphql', GQLTestBody::create([])));
    }

    #[Test]
    public function http4xxThrowsRequestResponseException(): void
    {
        $spy = new GQLSpyHttpClient(statusCode: 401);
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $this->expectException(RequestResponseException::class);

        $adapter->send(GQLTestAction::create('POST', '/graphql', GQLTestBody::create([])));
    }

    #[Test]
    public function http400ExactlyThrowsRequestResponseException(): void
    {
        $spy = new GQLSpyHttpClient(statusCode: 400);
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $this->expectException(RequestResponseException::class);

        $adapter->send(GQLTestAction::create('POST', '/graphql', GQLTestBody::create([])));
    }

    #[Test]
    public function http4xxStatusCodeIsPreservedWhenGetContentUsesThrowFalse(): void
    {
        $spy = new GQLSpyHttpClient(statusCode: 400, throwOnGetContentTrue: true);
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        try {
            $adapter->send(GQLTestAction::create('POST', '/graphql', GQLTestBody::create([])));
            self::fail('Expected RequestResponseException');
        } catch (RequestResponseException $e) {
            self::assertSame(400, $e->statusCode);
        }
    }

    #[Test]
    public function networkErrorDuringRequestIsWrappedInRequestResponseException(): void
    {
        $spy = new GQLSpyHttpClient(throwOnRequest: new \RuntimeException('Connection refused'));
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        try {
            $adapter->send(GQLTestAction::create('POST', '/graphql', GQLTestBody::create([])));
            self::fail('Expected RequestResponseException');
        } catch (RequestResponseException $e) {
            self::assertSame(0, $e->statusCode);
            self::assertStringContainsString('Connection refused', $e->context);
        }
    }

    #[Test]
    public function emptyErrorsArrayDoesNotThrow(): void
    {
        $spy = new GQLSpyHttpClient(responseBody: ['data' => ['user' => ['id' => '1']], 'errors' => []]);
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $result = $adapter->send(GQLTestAction::create('POST', '/graphql', GQLTestBody::create([])));

        self::assertSame(['user' => ['id' => '1']], $result);
    }

    #[Test]
    public function nonGraphQLBodyThrowsImmediately(): void
    {
        $spy = new GQLSpyHttpClient();
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $this->expectException(RequestResponseException::class);
        $this->expectExceptionMessageMatches('/GraphQLBodyInterface/');

        $adapter->send(GQLTestAction::create('POST', '/graphql', GQLNonGraphQLBody::create([])));
    }

    #[Test]
    public function nullBodyThrowsImmediately(): void
    {
        $spy = new GQLSpyHttpClient();
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $this->expectException(RequestResponseException::class);
        $this->expectExceptionMessageMatches('/GraphQLBodyInterface/');

        $adapter->send(GQLTestAction::create('POST', '/graphql'));
    }

    // ── Headers ───────────────────────────────────────────────────────────────

    #[Test]
    public function defaultHeadersAreSentWithContentType(): void
    {
        $spy = new GQLSpyHttpClient();
        $adapter = new GraphQLClientAdapter(
            httpClient: $spy,
            endpointUrl: 'https://api.example.com/graphql',
            defaultHeaders: ['X-Client' => 'test'],
        );

        $adapter->send(GQLTestAction::create('POST', '/graphql', GQLTestBody::create([])));

        /** @var array<string, string> $headers */
        $headers = $spy->lastOptions()['headers'];
        self::assertSame('test', $headers['X-Client']);
        self::assertSame('application/json', $headers['Content-Type']);
    }

    #[Test]
    public function bearerAuthHeaderIsApplied(): void
    {
        $spy = new GQLSpyHttpClient();
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $action = GQLTestAction::create(
            method: 'POST',
            path: '/graphql',
            body: GQLTestBody::create([]),
            authorization: new StaticAuthorizationConfig(type: 'bearer', params: ['token' => 'gh-token']),
        );

        $adapter->send($action);

        /** @var array<string, string> $headers */
        $headers = $spy->lastOptions()['headers'];
        self::assertSame('Bearer gh-token', $headers['Authorization']);
    }

    #[Test]
    public function callerHeaderOverridesAuthHeader(): void
    {
        $spy = new GQLSpyHttpClient();
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $action = GQLTestAction::create(
            method: 'POST',
            path: '/graphql',
            body: GQLTestBody::create([]),
            authorization: new StaticAuthorizationConfig(type: 'bearer', params: ['token' => 'engine-token']),
        );

        $callerHeaders = new class implements RequestHeadersInterface {
            public function toArray(): array
            {
                return ['Authorization' => 'Bearer caller-token'];
            }
        };

        $adapter->send($action, null, $callerHeaders);

        /** @var array<string, string> $headers */
        $headers = $spy->lastOptions()['headers'];
        self::assertSame('Bearer caller-token', $headers['Authorization']);
    }

    // ── ClientAdapterInterface capabilities ───────────────────────────────────

    #[Test]
    public function graphQLAdapterDoesNotRequirePath(): void
    {
        self::assertFalse(GraphQLClientAdapter::requiresPath());
    }

    #[Test]
    public function graphQLAdapterDoesNotRequireMethod(): void
    {
        self::assertFalse(GraphQLClientAdapter::requiresMethod());
    }

    #[Test]
    public function graphQLAdapterClientTypeIsGraphql(): void
    {
        self::assertSame('graphql', GraphQLClientAdapter::getClientType());
    }

    // ── statusCode en body inválido es exactamente 0 ──────────────────────────

    #[Test]
    public function nonGraphQLBodyExceptionHasStatusCodeZero(): void
    {
        $spy = new GQLSpyHttpClient();
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        try {
            $adapter->send(GQLTestAction::create('POST', '/graphql', GQLNonGraphQLBody::create([])));
            self::fail('Expected RequestResponseException');
        } catch (RequestResponseException $e) {
            self::assertSame(0, $e->statusCode);
            self::assertStringContainsString('GraphQLBodyInterface', $e->context);
        }
    }

    #[Test]
    public function nullBodyExceptionHasStatusCodeZero(): void
    {
        $spy = new GQLSpyHttpClient();
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        try {
            $adapter->send(GQLTestAction::create('POST', '/graphql'));
            self::fail('Expected RequestResponseException');
        } catch (RequestResponseException $e) {
            self::assertSame(0, $e->statusCode);
        }
    }

    // ── statusCode en error GraphQL es exactamente 200 ────────────────────────

    #[Test]
    public function graphQLErrorExceptionHasStatusCode200(): void
    {
        $spy = new GQLSpyHttpClient(responseBody: [
            'errors' => [['message' => 'something went wrong']],
            'data' => null,
        ]);
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        try {
            $adapter->send(GQLTestAction::create('POST', '/graphql', GQLTestBody::create([])));
            self::fail('Expected RequestResponseException');
        } catch (RequestResponseException $e) {
            self::assertSame(200, $e->statusCode);
        }
    }

    #[Test]
    public function graphQLErrorWithoutMessageExceptionHasStatusCode200(): void
    {
        $spy = new GQLSpyHttpClient(responseBody: [
            'errors' => [['extensions' => ['code' => 'UNAUTHENTICATED']]],
        ]);
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        try {
            $adapter->send(GQLTestAction::create('POST', '/graphql', GQLTestBody::create([])));
            self::fail('Expected RequestResponseException');
        } catch (RequestResponseException $e) {
            self::assertSame(200, $e->statusCode);
        }
    }
}

// ──────────────────────────────────────────────
// Inline fakes
// ──────────────────────────────────────────────

final class GQLSpyHttpClient implements HttpClientInterface
{
    private string $lastMethod = '';
    private string $lastUrl = '';

    /** @var array<string, mixed> */
    private array $lastOptions = [];

    /** @param array<string, mixed> $responseBody */
    public function __construct(
        private readonly array $responseBody = [],
        private readonly int $statusCode = 200,
        private readonly ?\Throwable $throwOnRequest = null,
        private readonly bool $throwOnGetContentTrue = false,
    ) {}

    public function lastMethod(): string
    {
        return $this->lastMethod;
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
        $this->lastMethod = $method;
        $this->lastUrl = $url;
        $this->lastOptions = $options;

        if (null !== $this->throwOnRequest) {
            throw $this->throwOnRequest;
        }

        $statusCode = $this->statusCode;
        $responseBody = $this->responseBody;
        $throwOnGetContentTrue = $this->throwOnGetContentTrue;

        return new class($statusCode, $responseBody, $throwOnGetContentTrue) implements HttpResponseInterface {
            /** @param array<string, mixed> $body */
            public function __construct(
                private readonly int $statusCode,
                private readonly array $body,
                private readonly bool $throwOnGetContentTrue,
            ) {}

            public function getStatusCode(): int
            {
                return $this->statusCode;
            }

            /** @return array<string, array<int, string>> */
            public function getHeaders(bool $throw = true): array
            {
                return [];
            }

            public function getContent(bool $throw = true): string
            {
                if ($throw && $this->throwOnGetContentTrue) {
                    throw new \RuntimeException('getContent called with throw: true');
                }

                return json_encode($this->body) ?: '{}';
            }

            /** @return array<mixed> */
            public function toArray(bool $throw = true): array
            {
                return $this->body;
            }

            public function cancel(): void
            {
                // Not implemented — test spy does not need to cancel requests
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

final class GQLTestBody implements GraphQLBodyInterface
{
    private function __construct()
    { // Intentionally empty: use factory method
    }

    public static function create(array $data): self
    {
        return new self();
    }

    public function getQuery(): string
    {
        return 'query { user { id } }';
    }

    /** @return array<string, mixed> */
    public function getVariables(): array
    {
        return ['login' => 'test'];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['query' => $this->getQuery(), 'variables' => $this->getVariables()];
    }
}

final class GQLNonGraphQLBody implements ActionBodyInterface
{
    private function __construct()
    { // Intentionally empty: use factory method
    }

    public static function create(array $data): self
    {
        return new self();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [];
    }
}

final class GQLTestAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'gql_test';
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
