<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\GraphQLBodyInterface;
use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Infrastructure\Http\GraphQLClientAdapter;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[Test]
    #[DataProvider('provideRestAdapterReportsAnUndecodableBodyWithItsHttpStatusNotAsANetworkErrorCases')]
    public function restAdapterReportsAnUndecodableBodyWithItsHttpStatusNotAsANetworkError(string $content): void
    {
        $adapter = new SymfonyHttpClientAdapter(
            httpClient: new MockHttpClient(new MockResponse($content, ['http_code' => 200])),
            baseUrl: 'https://api.example.com',
        );

        $error = $this->captureFailure(static fn () => $adapter->send(StatusCodeTestAction::create('GET', '/orders')));

        self::assertSame(200, $error->statusCode);
        self::assertStringStartsWith('GET /orders returned HTTP 200 with a body that is not a JSON object or array: ', $error->context);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideRestAdapterReportsAnUndecodableBodyWithItsHttpStatusNotAsANetworkErrorCases(): iterable
    {
        yield 'HTML page' => ['<html>maintenance</html>'];

        yield 'invalid JSON' => ['{"id":'];

        yield 'JSON scalar' => ['"ok"'];
    }

    #[Test]
    public function restAdapterBatchReportsAnUndecodableBodyWithItsHttpStatus(): void
    {
        $adapter = new SymfonyHttpClientAdapter(
            httpClient: new MockHttpClient([
                new MockResponse('<html>maintenance</html>', ['http_code' => 200]),
                new MockResponse('{"id":1}', ['http_code' => 200]),
            ]),
            baseUrl: 'https://api.example.com',
        );

        $results = $adapter->sendMany([
            'html' => new PreparedRequest(StatusCodeTestAction::create('GET', '/orders'), null, null),
            'json' => new PreparedRequest(StatusCodeTestAction::create('GET', '/orders'), null, null),
        ]);

        self::assertInstanceOf(RequestResponseException::class, $results['html']);
        self::assertSame(200, $results['html']->statusCode);
        self::assertStringContainsString('with a body that is not a JSON object or array', $results['html']->context);
        self::assertIsArray($results['json']);
    }

    #[Test]
    public function graphQLAdapterReportsAnUndecodableBodyWithItsHttpStatusNotAsANetworkError(): void
    {
        $adapter = new GraphQLClientAdapter(
            httpClient: new MockHttpClient(new MockResponse('<html>maintenance</html>', ['http_code' => 200])),
            endpointUrl: 'https://api.example.com/graphql',
        );

        $error = $this->captureFailure(static fn () => $adapter->send(StatusCodeTestAction::create('POST', '/', StatusCodeTestGraphQLBody::create([]))));

        self::assertSame(200, $error->statusCode);
        self::assertStringStartsWith('GraphQL endpoint https://api.example.com/graphql returned HTTP 200 with a body that is not a JSON object or array: ', $error->context);
    }

    #[Test]
    public function graphQLAdapterBatchReportsAnUndecodableBodyWithItsHttpStatus(): void
    {
        $adapter = new GraphQLClientAdapter(
            httpClient: new MockHttpClient(new MockResponse('<html>maintenance</html>', ['http_code' => 200])),
            endpointUrl: 'https://api.example.com/graphql',
        );

        $results = $adapter->sendMany([
            'html' => new PreparedRequest(StatusCodeTestAction::create('POST', '/', StatusCodeTestGraphQLBody::create([])), null, null),
        ]);

        self::assertInstanceOf(RequestResponseException::class, $results['html']);
        self::assertSame(200, $results['html']->statusCode);
        self::assertStringContainsString('with a body that is not a JSON object or array', $results['html']->context);
    }

    #[Test]
    public function aTransportFailureStillReportsStatusZero(): void
    {
        $adapter = new SymfonyHttpClientAdapter(
            httpClient: new MockHttpClient(new MockResponse('', ['error' => 'connection reset'])),
            baseUrl: 'https://api.example.com',
        );

        $error = $this->captureFailure(static fn () => $adapter->send(StatusCodeTestAction::create('GET', '/orders')));

        self::assertSame(0, $error->statusCode);
        self::assertStringStartsWith('Network error on GET /orders: ', $error->context);
    }

    private function captureFailure(callable $send): RequestResponseException
    {
        try {
            $send();
        } catch (RequestResponseException $e) {
            return $e;
        }

        self::fail('Expected a RequestResponseException.');
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
