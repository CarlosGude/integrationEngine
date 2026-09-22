<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\Action\ActionBodyInterface;
use IntegrationEngine\Core\Contract\Client\BodyEncoding;
use IntegrationEngine\Core\Contract\Client\Request;
use IntegrationEngine\Core\Contract\Client\RequestMiddlewareInterface;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use IntegrationEngine\Tests\Fake\FakeFormBody;
use IntegrationEngine\Tests\Fake\FakePathAction;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SymfonyHttpClientAdapterFormBodyTest extends TestCase
{
    public function testSendEncodesNestedFormAndPassesTimeout(): void
    {
        $response = new MockResponse('{}');
        $adapter = new SymfonyHttpClientAdapter(new MockHttpClient($response), 'https://example.com');
        $adapter->send(FakePathAction::create('POST', '/', FakeFormBody::create(['amount' => 2000, 'metadata' => ['movie_id' => '550']]), timeout: 1.5));
        $options = $response->getRequestOptions();
        self::assertSame('amount=2000&metadata%5Bmovie_id%5D=550', $options['body']);
        self::assertIsArray($options['headers']);
        self::assertContains('Content-Type: application/x-www-form-urlencoded', $options['headers']);
        self::assertSame(1.5, $options['timeout']);
    }

    public function testGetDoesNotSendFormBody(): void
    {
        $response = new MockResponse('{}');
        (new SymfonyHttpClientAdapter(new MockHttpClient($response), 'https://example.com'))->send(FakePathAction::create('GET', '/', FakeFormBody::create(['a' => 1])));
        self::assertArrayNotHasKey('body', $response->getRequestOptions());
    }

    public function testMiddlewareReceivesEncodingAndPreservesItWhenAddingHeader(): void
    {
        $response = new MockResponse('{}');
        $middleware = new class implements RequestMiddlewareInterface {
            public function handle(Request $request, callable $next): array
            {
                TestCase::assertSame(BodyEncoding::Form, $request->bodyEncoding);
                TestCase::assertSame(['amount' => 2000], $request->body);

                return $next($request->withHeader('X-Test', 'yes'));
            }
        };
        (new SymfonyHttpClientAdapter(new MockHttpClient($response), 'https://example.com', requestMiddlewares: [$middleware]))->send(FakePathAction::create('POST', '/', FakeFormBody::create(['amount' => 2000])));
        self::assertSame('amount=2000', $response->getRequestOptions()['body']);
        $headers = $response->getRequestOptions()['headers'];
        self::assertIsArray($headers);
        self::assertContains('X-Test: yes', $headers);
    }

    public function testFormSelectorSharesMiddlewareBatchAndRebasedTransport(): void
    {
        $response = new MockResponse('{}');
        $middleware = new class implements RequestMiddlewareInterface {
            public function handle(Request $request, callable $next): array
            {
                TestCase::assertSame(BodyEncoding::Form, $request->bodyEncoding);
                return $next($request->withHeader('X-Form', 'yes'));
            }
        };
        $adapter = new \IntegrationEngine\Infrastructure\Adapter\FormEncodedClientAdapter(new MockHttpClient($response), 'https://original.example', ['X-Default' => 'kept'], [$middleware]);
        $adapter->withBaseUrl('https://rebased.example')->sendMany([
            new PreparedRequest(FakePathAction::create('POST', '/', FakeFormBody::create(['amount' => 2000])), null, null),
        ]);
        self::assertSame('https://rebased.example/', $response->getRequestUrl());
        $headers = $response->getRequestOptions()['headers'];
        self::assertIsArray($headers);
        self::assertContains('X-Form: yes', $headers);
        self::assertContains('X-Default: kept', $headers);
    }

    public function testBatchEncodesMixedBodiesAndTimeout(): void
    {
        $form = new MockResponse('{}');
        $json = new MockResponse('{}');
        $jsonBody = new class implements ActionBodyInterface {
            public static function create(array $data): self
            {
                return new self();
            }

            public function toArray(): array
            {
                return ['currency' => 'eur'];
            }
        };
        $results = (new SymfonyHttpClientAdapter(new MockHttpClient([$form, $json]), 'https://example.com'))->sendMany([
            'form' => new PreparedRequest(FakePathAction::create('POST', '/', FakeFormBody::create(['amount' => 2000]), timeout: 2.0), null, null),
            'json' => new PreparedRequest(FakePathAction::create('POST', '/', $jsonBody), null, null),
        ]);
        self::assertSame(['form', 'json'], array_keys($results));
        self::assertSame('amount=2000', $form->getRequestOptions()['body']);
        self::assertSame(2.0, $form->getRequestOptions()['timeout']);
        self::assertSame('{"currency":"eur"}', $json->getRequestOptions()['body']);
    }
}
