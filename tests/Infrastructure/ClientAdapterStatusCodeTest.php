<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\GraphQLBodyInterface;
use IntegrationEngine\Infrastructure\Http\GraphQLClientAdapter;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The built-in adapters report the HTTP status alongside {body, headers};
 * the engine forwards it on HttpResponseReceived.
 */
final class ClientAdapterStatusCodeTest extends TestCase
{
    #[Test]
    public function restAdapterReportsTheResponseStatus(): void
    {
        $adapter = new SymfonyHttpClientAdapter(
            httpClient: new MockHttpClient(new MockResponse('{"id":1}', ['http_code' => 201])),
            baseUrl: 'https://api.example.com',
        );

        $result = $adapter->send(StatusCodeTestAction::create('POST', '/orders'));

        self::assertSame(201, $result['statusCode'] ?? null);
    }

    #[Test]
    public function graphQLAdapterReportsTheResponseStatus(): void
    {
        $adapter = new GraphQLClientAdapter(
            httpClient: new MockHttpClient(new MockResponse('{"data":{"ok":true}}', ['http_code' => 200])),
            endpointUrl: 'https://api.example.com/graphql',
        );

        $result = $adapter->send(StatusCodeTestAction::create('POST', '/', StatusCodeTestGraphQLBody::create([])));

        self::assertSame(200, $result['statusCode'] ?? null);
    }
}

final class StatusCodeTestAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'status_code_test';
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

final class StatusCodeTestGraphQLBody implements GraphQLBodyInterface
{
    public static function create(array $data): self
    {
        return new self();
    }

    public function toArray(): array
    {
        return [];
    }

    public function getQuery(): string
    {
        return '{ ok }';
    }

    public function getVariables(): array
    {
        return [];
    }
}
