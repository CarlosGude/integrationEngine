<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\GraphQLBodyInterface;
use IntegrationEngine\Infrastructure\Http\GraphQLClientAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

final class GraphQLClientAdapterBodyTest extends TestCase
{
    // ── Body serialisation ────────────────────────────────────────────────────

    #[Test]
    public function graphQLBodyIsSerialisedAsQueryAndVariables(): void
    {
        $spy = new GQLBodySpyClient();
        $adapter = new GraphQLClientAdapter(
            httpClient: $spy,
            endpointUrl: 'https://api.example.com/graphql',
        );

        $adapter->send(GQLBodyAction::create('POST', '/graphql', GQLBodyTestBody::create([])));

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
        $spy = new GQLBodySpyClient();
        $adapter = new GraphQLClientAdapter(
            httpClient: $spy,
            endpointUrl: 'https://api.example.com/graphql',
        );

        $adapter->send(GQLBodyAction::create('GET', '/some/other/path', GQLBodyTestBody::create([])));

        self::assertSame('https://api.example.com/graphql', $spy->lastUrl());
        self::assertSame('POST', $spy->lastMethod());
    }

    // ── Data extraction ───────────────────────────────────────────────────────

    #[Test]
    public function graphQLDataIsExtractedBeforeMapper(): void
    {
        $spy = new GQLBodySpyClient(responseBody: ['data' => ['user' => ['id' => '1', 'name' => 'Carlos']]]);
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $result = $adapter->send(GQLBodyAction::create('POST', '/graphql', GQLBodyTestBody::create([])));

        self::assertSame(['user' => ['id' => '1', 'name' => 'Carlos']], $result);
    }

    #[Test]
    public function nullDataKeyReturnsEmptyArray(): void
    {
        $spy = new GQLBodySpyClient(responseBody: ['data' => null]);
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $result = $adapter->send(GQLBodyAction::create('POST', '/graphql', GQLBodyTestBody::create([])));

        self::assertSame([], $result);
    }

    #[Test]
    public function emptyErrorsArrayDoesNotThrow(): void
    {
        $spy = new GQLBodySpyClient(responseBody: ['data' => ['user' => ['id' => '1']], 'errors' => []]);
        $adapter = new GraphQLClientAdapter(httpClient: $spy, endpointUrl: 'https://api.example.com/graphql');

        $result = $adapter->send(GQLBodyAction::create('POST', '/graphql', GQLBodyTestBody::create([])));

        self::assertSame(['user' => ['id' => '1']], $result);
    }

    // ── Adapter capabilities ──────────────────────────────────────────────────

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
}

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class GQLBodySpyClient implements HttpClientInterface
{
    private string $lastMethod = '';
    private string $lastUrl = '';

    /** @var array<string, mixed> */
    private array $lastOptions = [];

    /** @param array<string, mixed> $responseBody */
    public function __construct(private readonly array $responseBody = []) {}

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

        $body = $this->responseBody;

        return new class($body) implements HttpResponseInterface {
            /** @param array<string, mixed> $body */
            public function __construct(private readonly array $body) {}

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

final class GQLBodyTestBody implements GraphQLBodyInterface
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
        return ['login' => 'test'];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['query' => $this->getQuery(), 'variables' => $this->getVariables()];
    }
}

final class GQLBodyAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'gql_body_test';
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
