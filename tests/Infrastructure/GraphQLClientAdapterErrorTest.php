<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionBodyInterface;
use IntegrationEngine\Core\Contract\Action\GraphQLBodyInterface;
use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Infrastructure\Http\GraphQLClientAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

final class GraphQLClientAdapterErrorTest extends TestCase
{
    // ── GraphQL errors in payload ─────────────────────────────────────────────

    #[Test]
    public function graphQLErrorsInResponseThrowsWithStatusCode200(): void
    {
        $spy = new GQLErrorSpyClient(responseBody: [
            'errors' => [['message' => 'Field "user" not found']],
            'data' => null,
        ]);
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        try {
            $adapter->send(GQLErrorAction::create('POST', '/graphql', GQLErrorTestBody::create([])));
            self::fail('Expected RequestResponseException');
        } catch (RequestResponseException $e) {
            self::assertSame(200, $e->statusCode);
            self::assertStringContainsString('Field "user" not found', $e->getMessage());
        }
    }

    #[Test]
    public function graphQLErrorWithoutMessageThrowsGenericErrorWithStatusCode200(): void
    {
        $spy = new GQLErrorSpyClient(responseBody: [
            'errors' => [['extensions' => ['code' => 'UNAUTHENTICATED']]],
        ]);
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        try {
            $adapter->send(GQLErrorAction::create('POST', '/graphql', GQLErrorTestBody::create([])));
            self::fail('Expected RequestResponseException');
        } catch (RequestResponseException $e) {
            self::assertSame(200, $e->statusCode);
            self::assertStringContainsString('GraphQL error', $e->getMessage());
        }
    }

    // ── HTTP error status codes ───────────────────────────────────────────────

    #[Test]
    public function http4xxThrowsRequestResponseException(): void
    {
        $spy = new GQLErrorSpyClient(statusCode: 401);
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $this->expectException(RequestResponseException::class);

        $adapter->send(GQLErrorAction::create('POST', '/graphql', GQLErrorTestBody::create([])));
    }

    #[Test]
    public function http400ExactlyThrowsRequestResponseException(): void
    {
        $spy = new GQLErrorSpyClient(statusCode: 400);
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $this->expectException(RequestResponseException::class);

        $adapter->send(GQLErrorAction::create('POST', '/graphql', GQLErrorTestBody::create([])));
    }

    #[Test]
    public function http4xxStatusCodeIsPreservedWhenGetContentThrows(): void
    {
        $spy = new GQLErrorSpyClient(statusCode: 400, throwOnGetContentTrue: true);
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        try {
            $adapter->send(GQLErrorAction::create('POST', '/graphql', GQLErrorTestBody::create([])));
            self::fail('Expected RequestResponseException');
        } catch (RequestResponseException $e) {
            self::assertSame(400, $e->statusCode);
        }
    }

    #[Test]
    public function networkErrorDuringRequestIsWrappedWithStatusCodeZero(): void
    {
        $spy = new GQLErrorSpyClient(throwOnRequest: new \RuntimeException('Connection refused'));
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        try {
            $adapter->send(GQLErrorAction::create('POST', '/graphql', GQLErrorTestBody::create([])));
            self::fail('Expected RequestResponseException');
        } catch (RequestResponseException $e) {
            self::assertSame(0, $e->statusCode);
            self::assertStringContainsString('Connection refused', $e->context);
        }
    }

    // ── Invalid body ──────────────────────────────────────────────────────────

    #[Test]
    public function nonGraphQLBodyThrowsWithStatusCodeZero(): void
    {
        $spy = new GQLErrorSpyClient();
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        try {
            $adapter->send(GQLErrorAction::create('POST', '/graphql', GQLErrorNonGraphQLBody::create([])));
            self::fail('Expected RequestResponseException');
        } catch (RequestResponseException $e) {
            self::assertSame(0, $e->statusCode);
            self::assertStringContainsString('GraphQLBodyInterface', $e->context);
        }
    }

    #[Test]
    public function nullBodyThrowsWithStatusCodeZero(): void
    {
        $spy = new GQLErrorSpyClient();
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        try {
            $adapter->send(GQLErrorAction::create('POST', '/graphql'));
            self::fail('Expected RequestResponseException');
        } catch (RequestResponseException $e) {
            self::assertSame(0, $e->statusCode);
        }
    }
}

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class GQLErrorSpyClient implements HttpClientInterface
{
    /** @param array<string, mixed> $responseBody */
    public function __construct(
        private readonly array $responseBody = [],
        private readonly int $statusCode = 200,
        private readonly ?\Throwable $throwOnRequest = null,
        private readonly bool $throwOnGetContentTrue = false,
    ) {}

    /** @param array<string, mixed> $options */
    public function request(string $method, string $url, array $options = []): HttpResponseInterface
    {
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
                    throw new \LogicException('getContent called with throw: true');
                }

                return json_encode($this->body) ?: '{}';
            }

            /** @return array<mixed> */
            public function toArray(bool $throw = true): array
            {
                return $this->body;
            }

            public function cancel(): void
            { // No-op in test double
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

final class GQLErrorTestBody implements GraphQLBodyInterface
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
        return 'query { user { id } }';
    }

    /** @return array<string, mixed> */
    public function getVariables(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['query' => $this->getQuery(), 'variables' => $this->getVariables()];
    }
}

final class GQLErrorNonGraphQLBody implements ActionBodyInterface
{
    private function __construct()
    { // Intentionally empty: use factory method
    }

    /** @param array<string, mixed> $data */
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

final class GQLErrorAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'gql_error_test';
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
