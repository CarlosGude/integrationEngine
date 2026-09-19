<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;
use IntegrationEngine\Core\Contract\Webhook\WebhookMapperResolverPort;

/**
 * Test adapter for webhook mapper resolution.
 *
 * @author Carlos Gude
 */
final class WebhookMapperResolverAdapter implements WebhookMapperResolverPort
{
    /** @var array<string, AbstractWebhookMapper> */
    private array $mappers = [];

    /**
     * Register a mapper for an event type (for testing).
     *
     * @param string                $eventType Event type
     * @param AbstractWebhookMapper $mapper    The mapper
     */
    public function register(string $eventType, AbstractWebhookMapper $mapper): void
    {
        $this->mappers[$eventType] = $mapper;
    }

    public function resolveMapper(string $eventType): ?AbstractWebhookMapper
    {
        return $this->mappers[$eventType] ?? null;
    }

    /**
     * Clear all mappers (for testing).
     */
    public function clear(): void
    {
        $this->mappers = [];
    }
}

/**
 * Test webhook mapper implementation.
 */
final class TestWebhookMapperForDlq extends AbstractWebhookMapper
{
    public function __construct(
        private string $eventType = 'test/event',
    ) {}

    public function getDefinition(): string
    {
        return $this->eventType;
    }

    public function map(array $payload, array $headers): WebhookEventInterface
    {
        $id = 'unknown';
        if (isset($payload['id'])) {
            $value = $payload['id'];
            if (\is_string($value) || \is_int($value)) {
                $id = (string) $value;
            }
        }

        return new TestWebhookEventForDlq($id);
    }
}

/**
 * Test webhook event for DLQ.
 */
final class TestWebhookEventForDlq implements WebhookEventInterface
{
    public function __construct(
        public readonly string $id,
    ) {}
}

/**
 * Test webhook mapper that throws an error.
 */
final class TestWebhookMapperForDlqWithError extends AbstractWebhookMapper
{
    public function getDefinition(): string
    {
        return 'products/update';
    }

    public function map(array $payload, array $headers): WebhookEventInterface
    {
        throw new FakeFailure('Processing error: invalid payload');
    }
}
