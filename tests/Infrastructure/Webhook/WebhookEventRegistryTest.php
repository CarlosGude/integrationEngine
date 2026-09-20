<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Webhook;

use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;
use IntegrationEngine\Infrastructure\Webhook\WebhookEventRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The event type → DTO class lookup an application fills with its own events.
 */
final class WebhookEventRegistryTest extends TestCase
{
    private WebhookEventRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new WebhookEventRegistry();
        $this->registry->register('products/update', RegisteredProductUpdated::class);
        $this->registry->register('orders/create', RegisteredOrderCreated::class);
    }

    #[Test]
    public function resolvesARegisteredEventTypeToItsDtoClass(): void
    {
        self::assertSame(RegisteredProductUpdated::class, $this->registry->getEventClass('products/update'));
        self::assertTrue($this->registry->has('orders/create'));
        self::assertFalse($this->registry->has('customers/update'));
    }

    #[Test]
    public function listsTheRegisteredTypes(): void
    {
        self::assertSame(['products/update', 'orders/create'], $this->registry->listEventTypes());
    }

    #[Test]
    public function unknownEventTypeErrorNamesTheTypesOnOffer(): void
    {
        try {
            $this->registry->getEventClass('customers/update');
            self::fail('An unregistered event type must be rejected.');
        } catch (\InvalidArgumentException $error) {
            self::assertStringContainsString('Unknown webhook event type "customers/update"', $error->getMessage());
            // The message names the event types, not the DTO classes behind them.
            self::assertStringContainsString('Registered types: products/update, orders/create', $error->getMessage());
            self::assertStringNotContainsString(RegisteredProductUpdated::class, $error->getMessage());
        }
    }
}

final class RegisteredProductUpdated implements WebhookEventInterface {}

final class RegisteredOrderCreated implements WebhookEventInterface {}
