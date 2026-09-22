<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Infrastructure\Http\RetryStrategyFactory;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use IntegrationEngine\Tests\Fake\FakePathAction;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\RetryableHttpClient;

final class ResilienceBatchTest extends TestCase
{
    public function testRetriesDoNotSerialiseBatch(): void
    {
        $started = [];
        $firstConsumptions = [];
        $attempts = [];
        $http = new MockHttpClient(static function (string $method, string $url) use (&$started, &$firstConsumptions, &$attempts): MockResponse {
            $started[] = $url;
            $attempts[$url] = ($attempts[$url] ?? 0) + 1;
            $status = str_ends_with($url, '/0') && $attempts[$url] < 3 ? 503 : 200;
            $body = static function () use (&$started, &$firstConsumptions): \Generator {
                $firstConsumptions[] = \count($started);

                yield '{}';
            };

            return new MockResponse($body(), ['http_code' => $status]);
        });
        $adapter = new SymfonyHttpClientAdapter(new RetryableHttpClient($http, RetryStrategyFactory::create(['delay_ms' => 0])), 'https://example.com');
        $requests = array_map(static fn (int $id): PreparedRequest => new PreparedRequest(FakePathAction::create('GET', '/'.$id), null, null), range(0, 4));
        $results = $adapter->sendMany($requests);
        self::assertGreaterThanOrEqual(5, $firstConsumptions[0]);
        self::assertCount(7, $started);
        foreach ($results as $result) {
            self::assertIsArray($result);
            self::assertArrayHasKey('statusCode', $result);
            self::assertSame(200, $result['statusCode']);
        }
    }
}
