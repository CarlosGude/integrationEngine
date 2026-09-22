<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Exception\DisallowedHostException;
use IntegrationEngine\Core\Security\HostPolicy;
use IntegrationEngine\Infrastructure\Http\HostPolicyHttpClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HostPolicyHttpClientTest extends TestCase
{
    public function testForbiddenFinalDestinationNeverReachesTransport(): void
    {
        $inner = new MockHttpClient();
        $client = new HostPolicyHttpClient($inner, new HostPolicy(['partner.example']));

        try {
            $client->request('GET', 'https://evil.example');
            self::fail('Destination was not rejected.');
        } catch (DisallowedHostException) {
            self::assertSame(0, $inner->getRequestsCount());
        }
    }

    public function testAllowlistPreventsAutomaticRedirectBypassEvenWithOptions(): void
    {
        $inner = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame(0, $options['max_redirects']);

            return new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location: https://evil.example']]);
        });
        $client = (new HostPolicyHttpClient($inner, new HostPolicy(['partner.example'])))->withOptions(['max_redirects' => 20]);
        self::assertSame(302, $client->request('GET', 'https://partner.example')->getStatusCode());
    }
}
