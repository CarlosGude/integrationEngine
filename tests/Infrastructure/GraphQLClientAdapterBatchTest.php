<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\GraphQLBodyInterface;
use IntegrationEngine\Core\Contract\Client\Request;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use IntegrationEngine\Core\Contract\Client\RequestMiddlewareInterface;
use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Infrastructure\Http\GraphQLClientAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GraphQLClientAdapterBatchTest extends TestCase
{
    #[Test]
    public function dispatchesEveryRequestBeforeConsumingResponsesAndPreservesPayloadsAndKeys(): void
    {
        $events = new GQLBatchEvents();
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use ($events): MockResponse {
            $index = \count($events->log);
            $events->log[] = 'request '.$index;
            self::assertSame('POST', $method);
            self::assertSame('https://example.com/graphql', $url);
            self::assertIsString($options['body']);
            self::assertIsArray($options['headers']);
            self::assertSame(['query' => 'query { user { id } }', 'variables' => ['id' => $index]], json_decode($options['body'], true));
            self::assertContains('X-Item: '.$index, $options['headers']);

            return new MockResponse((static function () use ($events, $index): \Generator {
                $events->log[] = 'consume '.$index;

                yield json_encode(['data' => ['id' => $index]], JSON_THROW_ON_ERROR);
            })(), ['http_code' => 201, 'response_headers' => ['X-Trace: '.$index]]);
        });
        $adapter = new GraphQLClientAdapter($client, 'https://example.com/graphql');

        $results = $adapter->sendMany(['first' => $this->request(0), 7 => $this->request(1)]);

        self::assertSame(['request 0', 'request 1', 'consume 0', 'consume 1'], $events->log);
        self::assertSame([
            'first' => ['body' => ['id' => 0], 'headers' => ['x-trace' => ['0']], 'statusCode' => 201],
            7 => ['body' => ['id' => 1], 'headers' => ['x-trace' => ['1']], 'statusCode' => 201],
        ], $results);
    }

    #[Test]
    public function failuresDuringPreparationDispatchAndConsumptionDoNotAbortOrReorderTheBatch(): void
    {
        $calls = 0;
        $client = new MockHttpClient(static function () use (&$calls): MockResponse {
            return match ($calls++) {
                0 => new MockResponse('{"data":{"id":0}}'),
                1 => throw new \RuntimeException('connection refused'),
                2 => new MockResponse('{"errors":[{"message":"Unknown user"}],"data":{"id":2}}'),
                3 => new MockResponse('unavailable', ['http_code' => 503]),
                4 => new MockResponse((static function (): \Generator {
                    yield new \RuntimeException('connection lost');
                })()),
                default => new MockResponse('{"data":{"id":5}}'),
            };
        });
        $adapter = new GraphQLClientAdapter($client, 'https://example.com/graphql');
        $requests = [
            'first' => $this->request(0),
            12 => new PreparedRequest(GQLBatchAction::create('POST', '/ignored'), null, null),
            'dispatch' => $this->request(1),
            'graphql' => $this->request(2),
            'http' => $this->request(3),
            'consume' => $this->request(4),
            3 => $this->request(5),
        ];

        $results = $adapter->sendMany($requests);

        self::assertSame(array_keys($requests), array_keys($results));
        self::assertSame(6, $calls);
        self::assertSame(['body' => ['id' => 0], 'headers' => [], 'statusCode' => 200], $results['first']);
        self::assertSame(['body' => ['id' => 5], 'headers' => [], 'statusCode' => 200], $results[3]);
        foreach ([12 => [0, 'GraphQLBodyInterface'], 'dispatch' => [0, 'connection refused'], 'graphql' => [200, 'GraphQL error on action "gql_batch": Unknown user'], 'http' => [503, 'returned HTTP 503: unavailable'], 'consume' => [0, 'connection lost']] as $key => [$status, $message]) {
            $error = $results[$key];
            self::assertInstanceOf(RequestResponseException::class, $error);
            self::assertSame($status, $error->statusCode);
            self::assertStringContainsString($message, $error->getMessage());
        }
    }

    #[Test]
    public function middlewareFallbackRunsEachChainSequentiallyAndIsolatesShortCircuitsAndFailures(): void
    {
        $events = new GQLBatchEvents();
        $failure = new \DomainException('Rejected by signer');
        $middleware = new class($events, $failure) implements RequestMiddlewareInterface {
            public function __construct(private GQLBatchEvents $events, private \DomainException $failure) {}

            public function handle(Request $request, callable $next): array
            {
                $id = $request->headers['X-Item'];
                $this->events->log[] = 'before '.$id;
                if ('1' === $id) {
                    return ['body' => ['cached' => true], 'headers' => []];
                }
                if ('2' === $id) {
                    throw $this->failure;
                }
                $response = $next($request->withHeader('X-Signed', $id));
                $this->events->log[] = 'after '.$id;
                $response['body']['observed'] = $id;

                return $response;
            }
        };
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use ($events): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://tenant.example/graphql', $url);
            $events->log[] = 'transport';
            self::assertIsArray($options['headers']);
            self::assertIsString($options['body']);
            $body = json_decode($options['body'], true);
            self::assertIsArray($body);
            self::assertIsArray($body['variables']);
            self::assertIsInt($body['variables']['id']);
            self::assertContains('X-Default: kept', $options['headers']);
            self::assertContains('X-Signed: '.$body['variables']['id'], $options['headers']);

            return new MockResponse('{"data":{"ok":true}}');
        });
        $adapter = (new GraphQLClientAdapter($client, 'https://example.com/graphql', ['X-Default' => 'kept'], [$middleware]))->withBaseUrl('https://tenant.example/graphql');

        $results = $adapter->sendMany(['first' => $this->request(0), 'cached' => $this->request(1), 9 => $this->request(2), 'last' => $this->request(3)]);

        self::assertSame(['first', 'cached', 9, 'last'], array_keys($results));
        self::assertSame(['before 0', 'transport', 'after 0', 'before 1', 'before 2', 'before 3', 'transport', 'after 3'], $events->log);
        self::assertSame(['body' => ['ok' => true, 'observed' => '0'], 'headers' => [], 'statusCode' => 200], $results['first']);
        self::assertSame(['body' => ['cached' => true], 'headers' => []], $results['cached']);
        self::assertSame($failure, $results[9]);
        self::assertSame(['body' => ['ok' => true, 'observed' => '3'], 'headers' => [], 'statusCode' => 200], $results['last']);
    }

    #[Test]
    public function emptyBatchesDoNotDispatchRequestsOrInvokeMiddlewares(): void
    {
        $client = new MockHttpClient(static function (): never {
            self::fail('An empty batch must not dispatch HTTP requests.');
        });
        $middleware = new class implements RequestMiddlewareInterface {
            public function handle(Request $request, callable $next): array
            {
                TestCase::fail('An empty batch must not invoke middleware.');
            }
        };
        foreach ([[], [$middleware]] as $middlewares) {
            self::assertSame([], (new GraphQLClientAdapter($client, 'https://example.com/graphql', [], $middlewares))->sendMany([]));
        }
    }

    private function request(int $id): PreparedRequest
    {
        $headers = new class($id) implements RequestHeadersInterface {
            public function __construct(private int $id) {}

            public function toArray(): array
            {
                return ['X-Item' => (string) $this->id];
            }
        };

        return new PreparedRequest(GQLBatchAction::create('GET', '/ignored', GQLBatchBody::create(['id' => $id])), null, $headers);
    }
}

final class GQLBatchEvents
{
    /** @var list<string> */
    public array $log = [];
}

final class GQLBatchAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'gql_batch';
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

final readonly class GQLBatchBody implements GraphQLBodyInterface
{
    /** @param array<string, mixed> $variables */
    private function __construct(private array $variables) {}

    public static function create(array $data): self
    {
        return new self($data);
    }

    public function getQuery(): string
    {
        return 'query { user { id } }';
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function toArray(): array
    {
        return ['query' => $this->getQuery(), 'variables' => $this->variables];
    }
}
