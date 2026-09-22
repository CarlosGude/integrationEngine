<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Contract\Action\ActionBodyInterface;
use IntegrationEngine\Core\Contract\Auth\StaticAuthorizationConfig;
use IntegrationEngine\Core\Contract\Client\DynamicBaseUrlClientInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use IntegrationEngine\Core\Exception\PathResolutionException;
use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Infrastructure\Adapter\FormEncodedClientAdapter;
use IntegrationEngine\Tests\Fake\FakeContext;
use IntegrationEngine\Tests\Fake\FakePathAction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FormEncodedClientAdapterTest extends TestCase
{
    #[Test]
    public function declaresFormEncodingAndRequiredActionFields(): void
    {
        self::assertSame('form_encoded', FormEncodedClientAdapter::getClientType());
        self::assertTrue(FormEncodedClientAdapter::requiresPath());
        self::assertTrue(FormEncodedClientAdapter::requiresMethod());
    }

    #[Test]
    #[DataProvider('provideEncodesFormFieldsAndResolvesTheRequestPathCases')]
    public function encodesFormFieldsAndResolvesTheRequestPath(string $method): void
    {
        $response = new MockResponse('{"id":42}', ['http_code' => 201, 'response_headers' => ['X-Request-Id: abc', 'Set-Cookie: a=1', 'Set-Cookie: b=2']]);
        $adapter = new FormEncodedClientAdapter(new MockHttpClient($response), 'https://api.example.com');
        $body = FormEncodedTestBody::create(['name' => 'A & B', 'metadata' => ['reference' => 'x+y'], 'enabled' => true]);

        $result = $adapter->send(FakePathAction::create($method, '/orders/{id}', $body), FakeContext::create(['id' => 42]));

        self::assertSame($method, $response->getRequestMethod());
        self::assertSame('https://api.example.com/orders/42', $response->getRequestUrl());
        self::assertSame('name=A+%26+B&metadata%5Breference%5D=x%2By&enabled=1', $response->getRequestOptions()['body']);
        self::assertContains('Content-Type: application/x-www-form-urlencoded', self::requestHeaders($response));
        self::assertContains('Accept: application/json', self::requestHeaders($response));
        self::assertSame(['body' => ['id' => 42], 'headers' => ['x-request-id' => ['abc'], 'set-cookie' => ['a=1', 'b=2']], 'statusCode' => 201], $result);
    }

    /** @return iterable<string, array{string}> */
    public static function provideEncodesFormFieldsAndResolvesTheRequestPathCases(): iterable
    {
        foreach (['POST', 'PUT', 'PATCH'] as $method) {
            yield $method => [$method];
        }
    }

    #[Test]
    #[DataProvider('provideOmitsPayloadWhenTheMethodOrActionHasNoBodyCases')]
    public function omitsPayloadWhenTheMethodOrActionHasNoBody(string $method, bool $hasBody): void
    {
        $response = new MockResponse('{}');
        $adapter = new FormEncodedClientAdapter(new MockHttpClient($response), 'https://api.example.com');

        $adapter->send(FakePathAction::create($method, '/orders', $hasBody ? FormEncodedTestBody::create(['secret' => 'value']) : null));

        self::assertArrayNotHasKey('body', $response->getRequestOptions());
        $normalizedHeaders = $response->getRequestOptions()['normalized_headers'];
        self::assertIsArray($normalizedHeaders);
        self::assertArrayNotHasKey('content-type', $normalizedHeaders);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function provideOmitsPayloadWhenTheMethodOrActionHasNoBodyCases(): iterable
    {
        yield 'get' => ['GET', true];

        yield 'delete' => ['DELETE', true];

        yield 'post without body' => ['POST', false];
    }

    #[Test]
    public function authenticationOverridesDefaultsAndCallHeadersOverrideAuthentication(): void
    {
        $responses = [new MockResponse('{}'), new MockResponse('{}')];
        $adapter = new FormEncodedClientAdapter(new MockHttpClient($responses), 'https://api.example.com', ['Authorization' => 'default', 'X-Version' => '2', 'Accept' => 'application/vnd.example+json']);
        $action = FakePathAction::create('POST', '/orders', authorization: new StaticAuthorizationConfig('bearer', ['token' => 'action-token']));
        $adapter->send($action);
        $adapter->send($action, headers: new class implements RequestHeadersInterface {
            public function toArray(): array
            {
                return ['Authorization' => 'call-token', 'X-Version' => '3'];
            }
        });

        self::assertContains('Authorization: Bearer action-token', self::requestHeaders($responses[0]));
        self::assertContains('X-Version: 2', self::requestHeaders($responses[0]));
        self::assertContains('Accept: application/vnd.example+json', self::requestHeaders($responses[0]));
        self::assertContains('Authorization: call-token', self::requestHeaders($responses[1]));
        self::assertContains('X-Version: 3', self::requestHeaders($responses[1]));
    }

    #[Test]
    public function supportsRuntimeBaseUrlsWithoutChangingTheOriginalOrLosingHeaders(): void
    {
        $responses = [new MockResponse('{}'), new MockResponse('{}')];
        $original = new FormEncodedClientAdapter(new MockHttpClient($responses), 'https://original.example.com', ['X-Version' => '2']);

        self::assertContains(DynamicBaseUrlClientInterface::class, class_implements($original));
        $resolved = $original->withBaseUrl('https://tenant.example.com');
        $resolved->send(FakePathAction::create('GET', '/orders'));
        $original->send(FakePathAction::create('GET', '/orders'));

        self::assertNotSame($original, $resolved);
        self::assertSame('https://tenant.example.com/orders', $responses[0]->getRequestUrl());
        self::assertSame('https://original.example.com/orders', $responses[1]->getRequestUrl());
        self::assertContains('X-Version: 2', self::requestHeaders($responses[0]));
    }

    #[Test]
    #[DataProvider('provideAcceptsEmptyResponsesCases')]
    public function acceptsEmptyResponses(int $status, string $content): void
    {
        $adapter = new FormEncodedClientAdapter(new MockHttpClient(new MockResponse($content, ['http_code' => $status])), 'https://api.example.com');

        self::assertSame(['body' => [], 'headers' => [], 'statusCode' => $status], $adapter->send(FakePathAction::create('DELETE', '/orders/42')));
    }

    /** @return iterable<string, array{int, string}> */
    public static function provideAcceptsEmptyResponsesCases(): iterable
    {
        yield 'no content' => [204, ''];

        yield '204 with ignored content' => [204, '{"ignored":true}'];

        yield 'empty 200' => [200, ''];

        yield 'whitespace' => [200, " \n\t"];
    }

    #[Test]
    public function returnsAnEmptyRedirectResponseWithoutTreatingItAsATransportFailure(): void
    {
        $response = new MockResponse('', [
            'http_code' => 302,
            'response_headers' => ['Location: https://api.example.com/orders/42'],
        ]);
        $adapter = new FormEncodedClientAdapter(new MockHttpClient($response), 'https://api.example.com');

        $result = $adapter->send(FakePathAction::create('POST', '/orders'));

        self::assertSame([
            'body' => [],
            'headers' => ['location' => ['https://api.example.com/orders/42']],
            'statusCode' => 302,
        ], $result);
    }

    #[Test]
    #[DataProvider('providePreservesHttpFailuresAndTheirResponseDetailsCases')]
    public function preservesHttpFailuresAndTheirResponseDetails(int $status): void
    {
        $adapter = new FormEncodedClientAdapter(new MockHttpClient(new MockResponse('provider failure', ['http_code' => $status])), 'https://api.example.com');

        try {
            $adapter->send(FakePathAction::create('POST', '/orders'));
            self::fail('Expected HTTP failure.');
        } catch (RequestResponseException $e) {
            self::assertSame($status, $e->statusCode);
            self::assertSame('POST /orders returned HTTP '.$status.': provider failure', $e->context);
        }
    }

    /** @return iterable<array{int}> */
    public static function providePreservesHttpFailuresAndTheirResponseDetailsCases(): iterable
    {
        yield [400];

        yield [401];

        yield [503];
    }

    #[Test]
    public function wrapsTransportErrorsWithRequestDetails(): void
    {
        $client = new MockHttpClient(static function (): never {
            throw new \RuntimeException('Connection refused');
        });
        $adapter = new FormEncodedClientAdapter($client, 'https://api.example.com');

        try {
            $adapter->send(FakePathAction::create('POST', '/orders'));
            self::fail('Expected transport failure.');
        } catch (RequestResponseException $e) {
            self::assertSame(0, $e->statusCode);
            self::assertSame('Network error on POST /orders: Connection refused', $e->context);
        }
    }

    #[Test]
    public function preservesAnExistingRequestResponseException(): void
    {
        $original = new RequestResponseException(503, 'Upstream unavailable');
        $client = new MockHttpClient(static function () use ($original): never {
            throw $original;
        });
        $adapter = new FormEncodedClientAdapter($client, 'https://api.example.com');

        try {
            $adapter->send(FakePathAction::create('POST', '/orders'));
            self::fail('Expected existing HTTP failure.');
        } catch (RequestResponseException $e) {
            self::assertSame($original, $e);
        }
    }

    #[Test]
    public function wrapsFailuresWhileReadingTheResponse(): void
    {
        $content = (static function (): \Generator {
            yield new \RuntimeException('Connection reset');
        })();
        $adapter = new FormEncodedClientAdapter(new MockHttpClient(new MockResponse($content)), 'https://api.example.com');

        try {
            $adapter->send(FakePathAction::create('POST', '/orders'));
            self::fail('Expected response transport failure.');
        } catch (RequestResponseException $e) {
            self::assertSame(0, $e->statusCode);
            self::assertStringContainsString('Network error on POST /orders:', $e->context);
            self::assertStringContainsString('Connection reset', $e->context);
        }
    }

    #[Test]
    public function preservesPathErrorsBeforeCallingTheTransport(): void
    {
        $client = new MockHttpClient();
        $adapter = new FormEncodedClientAdapter($client, 'https://api.example.com');

        try {
            $adapter->send(FakePathAction::create('POST', '/orders/{id}'));
            self::fail('Expected missing path parameter.');
        } catch (PathResolutionException) {
            self::assertSame(0, $client->getRequestsCount());
        }
    }

    /** @return array<mixed> */
    private static function requestHeaders(MockResponse $response): array
    {
        $headers = $response->getRequestOptions()['headers'];
        self::assertIsArray($headers);

        return $headers;
    }
}

final readonly class FormEncodedTestBody implements ActionBodyInterface
{
    /** @param array<string, mixed> $data */
    private function __construct(private array $data) {}

    public static function create(array $data): self
    {
        return new self($data);
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
