<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Batch\EngineRequest;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Auth\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Contract\Auth\StaticAuthorizationConfig;
use IntegrationEngine\Core\Contract\Client\BatchClientInterface;
use IntegrationEngine\Core\Contract\Client\ClientInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use IntegrationEngine\Core\Contract\Mapper\AbstractMapper;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;
use IntegrationEngine\Core\Exception\ActionNotFoundException;
use IntegrationEngine\Core\Exception\MapperActionMismatchException;
use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Tests\Fake\FakePathAction;
use IntegrationEngine\Tests\Fake\FakeProtectedAction;
use IntegrationEngine\Tests\Fake\FakeTokenAction;
use IntegrationEngine\Tests\Fake\FakeTokenMapper;
use PHPUnit\Framework\Attributes\Test;

final class BatchSendSadPathTest extends IntegrationEngineTestCase
{
    // Trailing segment is hash('xxh128', '') — the baseUrl component of the key when no baseUrl is used.
    private const TOKEN_CACHE_KEY = 'integration_engine.token.test_integration.fake_fetch_token.99aa06d3014798d86001c324468d497f';

    // ── Partial failures ──────────────────────────────────────────────────────

    #[Test]
    public function sendManyReturnsPartialResultsWhenOneRequestFails(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/orders'));
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'tok']);
        $error = new RequestResponseException(statusCode: 500, context: 'GET /orders returned HTTP 500');
        $this->client->queueException(FakePathAction::getName(), $error);

        $results = $this->engine->sendMany([
            'ok' => new EngineRequest(FakeTokenAction::getName()),
            'broken' => new EngineRequest(FakePathAction::getName()),
        ]);

        self::assertTrue($results['ok']->isSuccess());
        self::assertFalse($results['broken']->isSuccess());
        self::assertSame($error, $results['broken']->error());
    }

    #[Test]
    public function sendManyCapturesUnknownActionAsFailureWithoutAbortingBatch(): void
    {
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/orders'));
        $this->client->setResponse(FakePathAction::getName(), []);

        $results = $this->engine->sendMany([
            'missing' => new EngineRequest('does_not_exist'),
            'ok' => new EngineRequest(FakePathAction::getName()),
        ]);

        self::assertFalse($results['missing']->isSuccess());
        self::assertInstanceOf(ActionNotFoundException::class, $results['missing']->error());
        self::assertTrue($results['ok']->isSuccess());
    }

    #[Test]
    public function sendManyCapturesMapperMismatchAsFailureWithoutAbortingBatch(): void
    {
        $this->config->register(BatchMismatchAction::getName(), BatchMismatchAction::create('GET', '/broken'));
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/orders'));
        $this->client->setResponse(BatchMismatchAction::getName(), []);
        $this->client->setResponse(FakePathAction::getName(), []);

        $results = $this->engine->sendMany([
            'mismatch' => new EngineRequest(BatchMismatchAction::getName()),
            'ok' => new EngineRequest(FakePathAction::getName()),
        ]);

        self::assertInstanceOf(MapperActionMismatchException::class, $results['mismatch']->error());
        self::assertTrue($results['ok']->isSuccess());
    }

    #[Test]
    public function sendManyOrFailThrowsTheFirstFailureInRequestOrder(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/orders'));
        $first = new RequestResponseException(statusCode: 500, context: 'first failure');
        $second = new RequestResponseException(statusCode: 502, context: 'second failure');
        $this->client->queueException(FakePathAction::getName(), $first);
        $this->client->queueException(FakeTokenAction::getName(), $second);

        try {
            $this->engine->sendManyOrFail([
                'a' => new EngineRequest(FakePathAction::getName()),
                'b' => new EngineRequest(FakeTokenAction::getName()),
            ]);
            self::fail('Expected the first failure to be thrown.');
        } catch (RequestResponseException $caught) {
            self::assertSame($first, $caught);
        }
    }

    // ── Dynamic auth in batch ─────────────────────────────────────────────────

    #[Test]
    public function sendManyResolvesDynamicTokenOncePerBatch(): void
    {
        $this->registerProtectedActionPair();
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'tok']);
        $this->client->setResponse(FakeProtectedAction::getName(), []);

        $results = $this->engine->sendMany([
            'one' => new EngineRequest(FakeProtectedAction::getName()),
            'two' => new EngineRequest(FakeProtectedAction::getName()),
        ]);

        self::assertTrue($results['one']->isSuccess());
        self::assertTrue($results['two']->isSuccess());
        self::assertSame(1, $this->client->callCount(FakeTokenAction::getName()));
        self::assertSame(2, $this->client->callCount(FakeProtectedAction::getName()));

        $auth = $this->client->lastAction()?->getAuthorization();
        self::assertInstanceOf(StaticAuthorizationConfig::class, $auth);
        self::assertSame('tok', $auth->params['token']);
    }

    #[Test]
    public function sendManyRetriesAllCachedToken401sWithOneFreshToken(): void
    {
        $this->registerProtectedActionPair();
        $this->cache->set(self::TOKEN_CACHE_KEY, 'stale_token', 60);
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'fresh_token']);
        $this->client->setResponse(FakeProtectedAction::getName(), []);
        $this->client->queueException(FakeProtectedAction::getName(), new RequestResponseException(statusCode: 401, context: 'unauthorized'));
        $this->client->queueException(FakeProtectedAction::getName(), new RequestResponseException(statusCode: 401, context: 'unauthorized'));

        $results = $this->engine->sendMany([
            'one' => new EngineRequest(FakeProtectedAction::getName()),
            'two' => new EngineRequest(FakeProtectedAction::getName()),
        ]);

        self::assertTrue($results['one']->isSuccess());
        self::assertTrue($results['two']->isSuccess());
        // One fresh fetch serves both retried items; 2 rejected + 2 retried sends.
        self::assertSame(1, $this->client->callCount(FakeTokenAction::getName()));
        self::assertSame(4, $this->client->callCount(FakeProtectedAction::getName()));
        self::assertSame('fresh_token', $this->cache->get(self::TOKEN_CACHE_KEY));
    }

    #[Test]
    public function sendManyDoesNotRetry401WhenTokenWasFetchedInThisBatch(): void
    {
        $this->registerProtectedActionPair();
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'fresh_token']);
        $this->client->queueException(FakeProtectedAction::getName(), new RequestResponseException(statusCode: 401, context: 'unauthorized'));
        $this->client->queueException(FakeProtectedAction::getName(), new RequestResponseException(statusCode: 401, context: 'unauthorized'));

        $results = $this->engine->sendMany([
            'one' => new EngineRequest(FakeProtectedAction::getName()),
            'two' => new EngineRequest(FakeProtectedAction::getName()),
        ]);

        // The token was fetched while preparing this batch — refetching it
        // would yield the same result, so the 401s are final for every item,
        // including the one that found the just-fetched token in the cache.
        self::assertFalse($results['one']->isSuccess());
        self::assertFalse($results['two']->isSuccess());
        self::assertSame(1, $this->client->callCount(FakeTokenAction::getName()));
        self::assertSame(2, $this->client->callCount(FakeProtectedAction::getName()));
    }

    #[Test]
    public function sendManyDoesNotRetryNon401FailuresEvenWithCachedToken(): void
    {
        $this->registerProtectedActionPair();
        $this->cache->set(self::TOKEN_CACHE_KEY, 'cached_token', 60);
        $error = new RequestResponseException(statusCode: 500, context: 'server error');
        $this->client->queueException(FakeProtectedAction::getName(), $error);

        $results = $this->engine->sendMany([
            'one' => new EngineRequest(FakeProtectedAction::getName()),
        ]);

        self::assertSame($error, $results['one']->error());
        self::assertSame(0, $this->client->callCount(FakeTokenAction::getName()));
        self::assertSame(1, $this->client->callCount(FakeProtectedAction::getName()));
        self::assertSame('cached_token', $this->cache->get(self::TOKEN_CACHE_KEY));
    }

    /**
     * Regression: BatchTokenRetry must key its pre-cache check and cache
     * invalidation by the item's baseUrl, exactly like DynamicAuthHandler
     * does when actually resolving the token. Before this fix, a stale
     * token cached under a baseUrl-scoped key was invisible to
     * BatchTokenRetry (which checked the bare, baseUrl-less key), so the
     * item was never marked retryable and the 401 propagated without ever
     * attempting the fresh-token retry.
     */
    #[Test]
    public function sendManyRetriesAStaleTokenCachedUnderABaseUrl(): void
    {
        $this->registerProtectedActionPair();
        $baseUrl = 'https://tenant-a.example.com';
        $cacheKey = 'integration_engine.token.test_integration.'.FakeTokenAction::getName().'.'.hash('xxh128', $baseUrl);
        $this->cache->set($cacheKey, 'stale_token', 60);
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'fresh_token']);
        $this->client->setResponse(FakeProtectedAction::getName(), []);
        $this->client->queueException(FakeProtectedAction::getName(), new RequestResponseException(statusCode: 401, context: 'unauthorized'));

        $results = $this->engine->sendMany([
            'one' => new EngineRequest(FakeProtectedAction::getName(), baseUrl: $baseUrl),
        ]);

        self::assertTrue($results['one']->isSuccess());
        self::assertSame(1, $this->client->callCount(FakeTokenAction::getName()));
        self::assertSame('fresh_token', $this->cache->get($cacheKey));
    }

    /**
     * Multi-connection requirement inside a single batch: an item whose
     * connection already has a cached token must reuse it without
     * refetching, while a different item's connection with nothing cached
     * triggers exactly one fetch — and neither write clobbers the other's
     * cache entry.
     */
    #[Test]
    public function sendManyKeepsTokenCacheIsolatedAcrossBaseUrlsInOneBatch(): void
    {
        $this->registerProtectedActionPair();
        $baseUrlA = 'https://tenant-a.example.com';
        $baseUrlB = 'https://tenant-b.example.com';
        $cacheKeyA = 'integration_engine.token.test_integration.'.FakeTokenAction::getName().'.'.hash('xxh128', $baseUrlA);
        $cacheKeyB = 'integration_engine.token.test_integration.'.FakeTokenAction::getName().'.'.hash('xxh128', $baseUrlB);
        $this->cache->set($cacheKeyA, 'token_a', 60);
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'token_b']);
        $this->client->setResponse(FakeProtectedAction::getName(), []);

        $results = $this->engine->sendMany([
            'a' => new EngineRequest(FakeProtectedAction::getName(), baseUrl: $baseUrlA),
            'b' => new EngineRequest(FakeProtectedAction::getName(), baseUrl: $baseUrlB),
        ]);

        self::assertTrue($results['a']->isSuccess());
        self::assertTrue($results['b']->isSuccess());
        // Tenant A's pre-cached token is reused — only tenant B fetches.
        self::assertSame(1, $this->client->callCount(FakeTokenAction::getName()));
        self::assertSame('token_b', $this->cache->get($cacheKeyB));
        // Tenant A's entry is untouched by tenant B's fetch.
        self::assertSame('token_a', $this->cache->get($cacheKeyA));
    }

    #[Test]
    public function sendManyFailsItemWhenTokenRefetchFailsDuringRetry(): void
    {
        $this->registerProtectedActionPair();
        $this->cache->set(self::TOKEN_CACHE_KEY, 'stale_token', 60);
        $this->client->queueException(FakeProtectedAction::getName(), new RequestResponseException(statusCode: 401, context: 'unauthorized'));
        $refetchError = new RequestResponseException(statusCode: 503, context: 'token endpoint down');
        $this->client->queueException(FakeTokenAction::getName(), $refetchError);

        $results = $this->engine->sendMany([
            'one' => new EngineRequest(FakeProtectedAction::getName()),
        ]);

        self::assertSame($refetchError, $results['one']->error());
        self::assertSame(1, $this->client->callCount(FakeTokenAction::getName()));
        self::assertSame(1, $this->client->callCount(FakeProtectedAction::getName()));
    }

    /**
     * Regression: retryBatch() must replace $prepared[$key] with the
     * retried PreparedRequest (rebuilt with the fresh token) before
     * buildResponse() runs — otherwise the mapper would see the stale,
     * cache-deleted pre-retry action.
     */
    #[Test]
    public function sendManyBuildsFinalResponseFromTheRetriedActionNotTheStaleOne(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(AuthEchoAction::getName(), AuthEchoAction::create('GET', '/echo', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
        $this->cache->set(self::TOKEN_CACHE_KEY, 'stale_token', 60);
        $this->client->setResponse(FakeTokenAction::getName(), ['access_token' => 'fresh_token']);
        $this->client->setResponse(AuthEchoAction::getName(), []);
        $this->client->queueException(AuthEchoAction::getName(), new RequestResponseException(statusCode: 401, context: 'unauthorized'));

        $results = $this->engine->sendMany([
            'one' => new EngineRequest(AuthEchoAction::getName()),
        ]);

        self::assertTrue($results['one']->isSuccess());
        $response = $results['one']->response();
        self::assertInstanceOf(AuthEchoResponse::class, $response);
        self::assertSame('fresh_token', $response->token());
    }

    // ── Batch client missing keys ─────────────────────────────────────────────

    #[Test]
    public function sendManyFailsKeysMissingFromABatchClientResponse(): void
    {
        $engine = new IntegrationEngine(
            config: $this->config,
            client: new FirstKeyOnlyBatchClient(),
            cache: $this->cache,
            integrationName: 'test_integration',
        );
        $this->config->register(FakePathAction::getName(), FakePathAction::create('GET', '/orders'));

        $results = $engine->sendMany([
            'kept' => new EngineRequest(FakePathAction::getName()),
            'dropped' => new EngineRequest(FakePathAction::getName()),
        ]);

        self::assertTrue($results['kept']->isSuccess());
        self::assertInstanceOf(\UnexpectedValueException::class, $results['dropped']->error());
        self::assertSame('Batch client returned no result for request "dropped".', $results['dropped']->error()->getMessage());
    }

    private function registerProtectedActionPair(): void
    {
        $this->config->register(FakeTokenAction::getName(), FakeTokenAction::create('GET', '/token'));
        $this->config->register(FakeProtectedAction::getName(), FakeProtectedAction::create('GET', '/protected', null, new DynamicAuthorizationConfig(
            action: FakeTokenAction::getName(),
            tokenField: 'access_token',
            ttl: 60,
        )));
    }
}

// ── Fixtures ──────────────────────────────────────────────────────────────────

/** Declares a mapper paired to a different action, to trip the mapper invariant. */
final class BatchMismatchAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'batch_mismatch_action';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): string
    {
        return FakeTokenMapper::class;
    }
}

/** Echoes back the bearer token its own authorization was built with, to observe which action instance the mapper actually received. */
final class AuthEchoResponse implements ResponseInterface
{
    public function __construct(private readonly string $token) {}

    public function token(): string
    {
        return $this->token;
    }

    public function toArray(): array
    {
        return ['token' => $this->token];
    }
}

final class AuthEchoMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return AuthEchoAction::class;
    }

    protected static function transform(AbstractAction $action, array $response, array $headers): ResponseInterface
    {
        $auth = $action->getAuthorization();
        $tokenValue = $auth instanceof StaticAuthorizationConfig ? ($auth->params['token'] ?? '') : '';
        $token = \is_string($tokenValue) ? $tokenValue : '';

        return new AuthEchoResponse($token);
    }
}

final class AuthEchoAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'auth_echo_action';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): string
    {
        return AuthEchoMapper::class;
    }
}

/** A misbehaving batch client that drops every key but the first. */
final class FirstKeyOnlyBatchClient implements BatchClientInterface, ClientInterface
{
    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
        return ['body' => [], 'headers' => []];
    }

    public function sendMany(array $requests): array
    {
        $firstKey = array_key_first($requests);

        return null !== $firstKey ? [$firstKey => ['body' => [], 'headers' => []]] : [];
    }
}
