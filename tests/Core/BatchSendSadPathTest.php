<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Batch\EngineRequest;
use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\ActionContextInterface;
use IntegrationEngine\Core\Contract\BatchClientInterface;
use IntegrationEngine\Core\Contract\ClientInterface;
use IntegrationEngine\Core\Contract\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Contract\RequestHeadersInterface;
use IntegrationEngine\Core\Contract\StaticAuthorizationConfig;
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
    private const TOKEN_CACHE_KEY = 'integration_engine.token.test_integration.fake_fetch_token';

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
            'ok' => EngineRequest::create(FakeTokenAction::getName()),
            'broken' => EngineRequest::create(FakePathAction::getName()),
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
            'missing' => EngineRequest::create('does_not_exist'),
            'ok' => EngineRequest::create(FakePathAction::getName()),
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
            'mismatch' => EngineRequest::create(BatchMismatchAction::getName()),
            'ok' => EngineRequest::create(FakePathAction::getName()),
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
                'a' => EngineRequest::create(FakePathAction::getName()),
                'b' => EngineRequest::create(FakeTokenAction::getName()),
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
            'one' => EngineRequest::create(FakeProtectedAction::getName()),
            'two' => EngineRequest::create(FakeProtectedAction::getName()),
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
            'one' => EngineRequest::create(FakeProtectedAction::getName()),
            'two' => EngineRequest::create(FakeProtectedAction::getName()),
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
            'one' => EngineRequest::create(FakeProtectedAction::getName()),
            'two' => EngineRequest::create(FakeProtectedAction::getName()),
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
            'one' => EngineRequest::create(FakeProtectedAction::getName()),
        ]);

        self::assertSame($error, $results['one']->error());
        self::assertSame(0, $this->client->callCount(FakeTokenAction::getName()));
        self::assertSame(1, $this->client->callCount(FakeProtectedAction::getName()));
        self::assertSame('cached_token', $this->cache->get(self::TOKEN_CACHE_KEY));
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
            'one' => EngineRequest::create(FakeProtectedAction::getName()),
        ]);

        self::assertSame($refetchError, $results['one']->error());
        self::assertSame(1, $this->client->callCount(FakeTokenAction::getName()));
        self::assertSame(1, $this->client->callCount(FakeProtectedAction::getName()));
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
            'kept' => EngineRequest::create(FakePathAction::getName()),
            'dropped' => EngineRequest::create(FakePathAction::getName()),
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

/** A misbehaving batch client that drops every key but the first. */
final class FirstKeyOnlyBatchClient implements BatchClientInterface, ClientInterface
{
    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
        return [];
    }

    public function sendMany(array $requests): array
    {
        $firstKey = array_key_first($requests);

        return null !== $firstKey ? [$firstKey => []] : [];
    }
}
