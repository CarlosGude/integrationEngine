<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Infrastructure\Http\RetryStrategyFactory;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use IntegrationEngine\Tests\Fake\FakeLogger;
use IntegrationEngine\Tests\Fake\FakePathAction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\RetryableHttpClient;

final class ResilienceBehaviourTest extends TestCase
{
    #[DataProvider('provideRetryPolicyCases')]
    public function testRetryPolicy(string $method, bool $optIn, int $status, int $expected): void
    {
        $http = new MockHttpClient([new MockResponse('{}', ['http_code' => $status]), new MockResponse('{}', ['http_code' => $status]), new MockResponse('{"ok":true}')]);
        $adapter = new SymfonyHttpClientAdapter(new RetryableHttpClient($http, RetryStrategyFactory::create(['delay_ms' => 0, 'retry_non_idempotent' => $optIn])), 'https://example.com');

        try {
            self::assertSame(['ok' => true], $adapter->send(FakePathAction::create($method, '/'))['body']);
            self::assertSame(3, $expected);
        } catch (RequestResponseException $e) {
            self::assertSame($status, $e->statusCode);
            self::assertSame(1, $expected);
        }
        self::assertSame($expected, $http->getRequestsCount());
    }

    /** @return iterable<string, array{string, bool, int, int}> */
    public static function provideRetryPolicyCases(): iterable
    {
        yield 'GET transient' => ['GET', false, 503, 3];

        yield 'POST no opt-in' => ['POST', false, 503, 1];

        yield 'POST opt-in' => ['POST', true, 503, 3];

        yield 'unconfigured status' => ['GET', false, 400, 1];
    }

    public function testRetryAfterUsesPauseHandlerWithoutSleeping(): void
    {
        $delays = [];
        $pause = static function (float $duration) use (&$delays): void { $delays[] = $duration; };
        $http = new MockHttpClient([new MockResponse('{}', ['http_code' => 429, 'response_headers' => ['Retry-After: 2']]), new MockResponse('{}', ['pause_handler' => $pause])]);
        $logger = new FakeLogger();
        $adapter = new SymfonyHttpClientAdapter(new RetryableHttpClient($http, RetryStrategyFactory::create(['delay_ms' => 0]), 3, $logger), 'https://example.com');
        $adapter->send(FakePathAction::create('GET', '/'));
        self::assertSame([2.0], $delays);
        self::assertSame(2000, $logger->contextFor('info', 'Try #')['delay']);
    }

    public function testTransientTransportErrorRetriesGet(): void
    {
        $http = new MockHttpClient([new MockResponse('', ['error' => 'connection reset', 'primary_ip' => '192.0.2.1', 'http_code' => 0]), new MockResponse('{"ok":true}')]);
        $adapter = new SymfonyHttpClientAdapter(new RetryableHttpClient($http, RetryStrategyFactory::create(['delay_ms' => 0])), 'https://example.com');
        self::assertSame(['ok' => true], $adapter->send(FakePathAction::create('GET', '/'))['body']);
        self::assertSame(2, $http->getRequestsCount());
    }

    public function testNetworkFailureBecomesStatusZero(): void
    {
        $http = new MockHttpClient(static function (): never {
            throw new TransportException('timed out');
        });
        $adapter = new SymfonyHttpClientAdapter(new RetryableHttpClient($http, RetryStrategyFactory::create(['delay_ms' => 0])), 'https://example.com');

        try {
            $adapter->send(FakePathAction::create('GET', '/'));
            self::fail('Expected network failure');
        } catch (RequestResponseException $e) {
            self::assertSame(0, $e->statusCode);
        }
    }

    public function testStopsAfterConfiguredRetryCount(): void
    {
        $http = new MockHttpClient(array_map(static fn (): MockResponse => new MockResponse('{}', ['http_code' => 503]), range(1, 4)));
        $adapter = new SymfonyHttpClientAdapter(new RetryableHttpClient($http, RetryStrategyFactory::create(['delay_ms' => 0]), 3), 'https://example.com');

        try {
            $adapter->send(FakePathAction::create('GET', '/'));
            self::fail('Expected failure after retries');
        } catch (RequestResponseException $e) {
            self::assertSame(503, $e->statusCode);
            self::assertSame(4, $http->getRequestsCount());
        }
    }
}
