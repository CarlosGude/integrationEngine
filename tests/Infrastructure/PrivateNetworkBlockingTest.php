<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use IntegrationEngine\Tests\Fake\FakePathAction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PrivateNetworkBlockingTest extends TestCase
{
    #[DataProvider('providePrivateAddressIsWrappedAsNetworkFailureCases')]
    public function testPrivateAddressIsWrappedAsNetworkFailure(string $baseUrl, string $ip): void
    {
        // Symfony 6.4 checks the connected IP through on_progress. Modern
        // versions additionally reject the literal before reaching this fake.
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use ($ip): MockResponse {
            self::assertIsCallable($options['on_progress']);
            $options['on_progress'](0, 0, ['primary_ip' => $ip, 'url' => $url]);

            return new MockResponse('{}');
        });
        $adapter = new SymfonyHttpClientAdapter(new NoPrivateNetworkHttpClient($http), $baseUrl);

        try {
            $adapter->send(FakePathAction::create('GET', '/latest/meta-data'));
            self::fail('Private address must be blocked');
        } catch (RequestResponseException $e) {
            self::assertSame(0, $e->statusCode);
            self::assertStringContainsString('blocked', $e->context);
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function providePrivateAddressIsWrappedAsNetworkFailureCases(): iterable
    {
        yield 'loopback' => ['http://127.0.0.1', '127.0.0.1'];

        yield 'private' => ['http://10.0.0.5', '10.0.0.5'];

        yield 'metadata' => ['http://169.254.169.254', '169.254.169.254'];

        yield 'ipv6 loopback' => ['http://[::1]', '::1'];
    }

    public function testRedirectToPrivateAddressIsBlocked(): void
    {
        $http = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertIsCallable($options['on_progress']);
            $options['on_progress'](0, 0, ['primary_ip' => '93.184.216.34', 'url' => $url]);
            if (0 !== $options['max_redirects']) {
                // Older Symfony delegates following redirects to its transport;
                // emulate its next connected address through progress reporting.
                $options['on_progress'](0, 0, ['primary_ip' => '127.0.0.1', 'url' => 'http://127.0.0.1/private']);
            }

            // Modern Symfony follows this response itself and rejects the next
            // host before dispatch. MockHttpClient does not implement redirects.
            return new MockResponse('', ['http_code' => 302, 'redirect_url' => 'http://127.0.0.1/private', 'response_headers' => ['Location: http://127.0.0.1/private']]);
        });
        $adapter = new SymfonyHttpClientAdapter(new NoPrivateNetworkHttpClient($http), 'http://93.184.216.34');

        try {
            $adapter->send(FakePathAction::create('GET', '/'));
            self::fail('Private redirect must be blocked');
        } catch (RequestResponseException $e) {
            self::assertSame(0, $e->statusCode);
            self::assertStringContainsString('blocked', $e->context);
            self::assertSame(1, $http->getRequestsCount());
        }
    }
}
