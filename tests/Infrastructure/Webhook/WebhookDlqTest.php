<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Webhook;

use IntegrationEngine\Core\Webhook\WebhookFailure;
use IntegrationEngine\Infrastructure\Webhook\Handler\ProcessWebhookHandler;
use IntegrationEngine\Infrastructure\Webhook\Message\ProcessWebhookMessage;
use IntegrationEngine\Tests\Fake\FakeFailure;
use IntegrationEngine\Tests\Fake\TestWebhookMapperForDlq;
use IntegrationEngine\Tests\Fake\TestWebhookMapperForDlqWithError;
use IntegrationEngine\Tests\Fake\WebhookDlqAdapter;
use IntegrationEngine\Tests\Fake\WebhookMapperResolverAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Tests for webhook dead-letter queue and retry handling.
 *
 * @author Carlos Gude
 */
final class WebhookDlqTest extends TestCase
{
    private WebhookDlqAdapter $dlq;
    private WebhookMapperResolverAdapter $resolverAdapter;
    private ProcessWebhookHandler $handler;

    protected function setUp(): void
    {
        $this->dlq = new WebhookDlqAdapter();
        $this->resolverAdapter = new WebhookMapperResolverAdapter();
        $eventDispatcher = new EventDispatcher();
        $this->handler = new ProcessWebhookHandler(
            $eventDispatcher,
            $this->resolverAdapter,
            $this->dlq,
        );
    }

    public function testSuccessfulWebhookProcessing(): void
    {
        $mapper = new TestWebhookMapperForDlq('products/update');
        $this->resolverAdapter->register('products/update', $mapper);

        $message = new ProcessWebhookMessage(
            eventType: 'products/update',
            payload: ['id' => 123, 'title' => 'Product'],
            headers: ['X-Shopify-Topic' => 'products/update'],
        );

        // Process the message
        ($this->handler)($message);

        // No failures should be stored
        $unresolved = $this->dlq->findUnresolved();
        if ($unresolved) {
            self::fail('Expected no failures, but got: '.$unresolved[0]->errorMessage);
        }
        self::assertSame(0, $this->dlq->countUnresolved());
    }

    public function testFailedWebhookStoresInDlq(): void
    {
        // Register a mapper that throws an exception
        $throwingMapper = new TestWebhookMapperForDlqWithError();
        $this->resolverAdapter->register('products/update', $throwingMapper);

        $message = new ProcessWebhookMessage(
            eventType: 'products/update',
            payload: ['id' => 123, 'title' => 'Product'],
            headers: [],
        );

        // Process the message (should not throw)
        ($this->handler)($message);

        // Failure should be stored
        self::assertSame(1, $this->dlq->countUnresolved());

        $failures = $this->dlq->findUnresolved();
        self::assertCount(1, $failures);

        $failure = $failures[0];
        self::assertSame('products/update', $failure->eventType);
        self::assertSame(123, $failure->payload['id']);
        self::assertStringContainsString('invalid payload', $failure->errorMessage);
        self::assertSame(FakeFailure::class, $failure->errorClass);
        self::assertSame(0, $failure->retryCount);
    }

    public function testFailureIdsAreDistinctV4Uuids(): void
    {
        $this->resolverAdapter->register('products/update', new TestWebhookMapperForDlqWithError());

        $message = new ProcessWebhookMessage(
            eventType: 'products/update',
            payload: ['id' => 123],
            headers: [],
        );

        ($this->handler)($message);
        ($this->handler)($message);

        $failures = $this->dlq->findUnresolved();
        self::assertCount(2, $failures);

        foreach ($failures as $failure) {
            self::assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                $failure->id,
            );
        }
        self::assertNotSame($failures[0]->id, $failures[1]->id);
    }

    public function testUnknownEventTypeIsIgnored(): void
    {
        $message = new ProcessWebhookMessage(
            eventType: 'unknown/event',
            payload: ['id' => 123],
            headers: [],
        );

        // Process the message
        ($this->handler)($message);

        // No failures should be stored for unknown event types
        self::assertSame(0, $this->dlq->countUnresolved());
    }

    public function testFailureCanBeRetrieved(): void
    {
        $failure = new WebhookFailure(
            id: 'test-failure-id',
            eventType: 'products/update',
            payload: ['id' => 123],
            errorMessage: 'Test error',
            errorClass: \RuntimeException::class,
            createdAt: new \DateTimeImmutable(),
        );

        $this->dlq->store($failure);

        // Retrieve by ID
        $retrieved = $this->dlq->findById('test-failure-id');
        self::assertNotNull($retrieved);
        self::assertSame('test-failure-id', $retrieved->id);
        self::assertSame('products/update', $retrieved->eventType);
    }

    public function testFailureCanBeResolved(): void
    {
        $failure = new WebhookFailure(
            id: 'test-failure-id',
            eventType: 'products/update',
            payload: ['id' => 123],
            errorMessage: 'Test error',
            errorClass: \RuntimeException::class,
            createdAt: new \DateTimeImmutable(),
        );

        $this->dlq->store($failure);
        self::assertSame(1, $this->dlq->countUnresolved());

        // Resolve the failure
        $resolved = $this->dlq->resolve('test-failure-id');
        self::assertTrue($resolved);

        // Should now be gone from unresolved list
        self::assertSame(0, $this->dlq->countUnresolved());
    }

    public function testRetryIncrementCount(): void
    {
        $failure = new WebhookFailure(
            id: 'test-failure-id',
            eventType: 'orders/create',
            payload: ['id' => 999],
            errorMessage: 'Temporary error',
            errorClass: \RuntimeException::class,
            createdAt: new \DateTimeImmutable(),
            retryCount: 0,
        );

        $this->dlq->store($failure);

        // Create retry attempt
        $retried = $failure->withRetry();
        self::assertSame(1, $retried->retryCount);
        self::assertNotNull($retried->lastAttemptAt);

        // Record retry
        $this->dlq->recordRetry($retried);

        // Verify retry was recorded
        $updated = $this->dlq->findById('test-failure-id');
        self::assertNotNull($updated);
        self::assertSame(1, $updated->retryCount);
    }

    public function testMultipleFailuresOrderedByCreation(): void
    {
        $now = new \DateTimeImmutable();

        $failure1 = new WebhookFailure(
            id: 'failure-1',
            eventType: 'event/a',
            payload: [],
            errorMessage: 'Error A',
            errorClass: \RuntimeException::class,
            createdAt: $now,
        );

        $failure2 = new WebhookFailure(
            id: 'failure-2',
            eventType: 'event/b',
            payload: [],
            errorMessage: 'Error B',
            errorClass: \RuntimeException::class,
            createdAt: $now->modify('+1 second'),
        );

        $failure3 = new WebhookFailure(
            id: 'failure-3',
            eventType: 'event/c',
            payload: [],
            errorMessage: 'Error C',
            errorClass: \RuntimeException::class,
            createdAt: $now->modify('+2 seconds'),
        );

        // Store in different order
        $this->dlq->store($failure3);
        $this->dlq->store($failure1);
        $this->dlq->store($failure2);

        $unresolved = $this->dlq->findUnresolved();
        self::assertCount(3, $unresolved);

        // Should be ordered by creation time (oldest first)
        self::assertSame('failure-1', $unresolved[0]->id);
        self::assertSame('failure-2', $unresolved[1]->id);
        self::assertSame('failure-3', $unresolved[2]->id);
    }

    public function testWebhookFailureFromThrowable(): void
    {
        $error = new \RuntimeException('Something went wrong');
        $failure = WebhookFailure::fromThrowable(
            'test-id',
            'products/update',
            ['id' => 123],
            $error,
        );

        self::assertSame('test-id', $failure->id);
        self::assertSame('products/update', $failure->eventType);
        self::assertSame('Something went wrong', $failure->errorMessage);
        self::assertSame(\RuntimeException::class, $failure->errorClass);
        self::assertSame(0, $failure->retryCount);
    }
}
