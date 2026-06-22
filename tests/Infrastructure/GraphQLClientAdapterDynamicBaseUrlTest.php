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

final class GraphQLClientAdapterDynamicBaseUrlTest extends TestCase
{
    #[Test]
    public function withBaseUrlReturnsANewInstanceWithoutMutatingTheOriginal(): void
    {
        $spy = new GQLDynBaseUrlSpyClient();
        $original = new GraphQLClientAdapter($spy, 'https://original.example.com/graphql');

        $resolved = $original->withBaseUrl('https://tenant-one.example.com/graphql');

        self::assertNotSame($original, $resolved);

        $original->send(GQLDynBaseUrlAction::create('POST', '/graphql', GQLDynBaseUrlBody::create([])));
        self::assertSame('https://original.example.com/graphql', $spy->lastUrl());
    }

    #[Test]
    public function requestAfterWithBaseUrlHitsTheNewEndpointNotTheOriginal(): void
    {
        $spy = new GQLDynBaseUrlSpyClient();
        $original = new GraphQLClientAdapter($spy, 'https://original.example.com/graphql');

        $resolved = $original->withBaseUrl('https://tenant-one.example.com/graphql');
        $resolved->send(GQLDynBaseUrlAction::create('POST', '/graphql', GQLDynBaseUrlBody::create([])));

        self::assertSame('https://tenant-one.example.com/graphql', $spy->lastUrl());
    }
}

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class GQLDynBaseUrlSpyClient implements HttpClientInterface
{
    private string $lastUrl = '';

    public function lastUrl(): string
    {
        return $this->lastUrl;
    }

    /** @param array<string, mixed> $options */
    public function request(string $method, string $url, array $options = []): HttpResponseInterface
    {
        $this->lastUrl = $url;

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
                return '{"data":{}}';
            }

            /** @return array<mixed> */
            public function toArray(bool $throw = true): array
            {
                return ['data' => []];
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
        throw new \LogicException('Not implemented.');
    }

    /** @param array<string, mixed> $options */
    public function withOptions(array $options): static
    {
        return $this;
    }
}

final class GQLDynBaseUrlBody implements GraphQLBodyInterface
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

final class GQLDynBaseUrlAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'gql_dyn_base_url_test';
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
