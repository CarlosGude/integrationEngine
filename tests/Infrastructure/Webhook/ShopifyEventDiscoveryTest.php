<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Webhook;

use IntegrationEngine\Infrastructure\Webhook\Event\ShopifyCustomerUpdated;
use IntegrationEngine\Infrastructure\Webhook\Event\ShopifyInventoryUpdated;
use IntegrationEngine\Infrastructure\Webhook\Event\ShopifyOrderCreated;
use IntegrationEngine\Infrastructure\Webhook\Event\ShopifyProductUpdated;
use IntegrationEngine\Infrastructure\Webhook\Mapper\ShopifyCustomerUpdatedMapper;
use IntegrationEngine\Infrastructure\Webhook\Mapper\ShopifyInventoryUpdatedMapper;
use IntegrationEngine\Infrastructure\Webhook\Mapper\ShopifyOrderCreatedMapper;
use IntegrationEngine\Infrastructure\Webhook\Mapper\ShopifyProductUpdatedMapper;
use IntegrationEngine\Infrastructure\Webhook\WebhookEventRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for Shopify webhook event discovery and mapping.
 *
 * Tests that the registry can route webhook event types to correct mappers,
 * and that real Shopify payloads are correctly parsed into typed DTOs.
 *
 * @author Carlos Gude
 */
final class ShopifyEventDiscoveryTest extends TestCase
{
    private WebhookEventRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new WebhookEventRegistry();

        // Register Shopify event types
        $this->registry->register('products/update', ShopifyProductUpdated::class);
        $this->registry->register('orders/create', ShopifyOrderCreated::class);
        $this->registry->register('customers/update', ShopifyCustomerUpdated::class);
        $this->registry->register('inventory_levels/update', ShopifyInventoryUpdated::class);
    }

    public function testRegistryContainsAllShopifyEvents(): void
    {
        self::assertTrue($this->registry->has('products/update'));
        self::assertTrue($this->registry->has('orders/create'));
        self::assertTrue($this->registry->has('customers/update'));
        self::assertTrue($this->registry->has('inventory_levels/update'));

        $types = $this->registry->listEventTypes();
        self::assertCount(4, $types);
    }

    public function testRegistryThrowsOnUnknownEventType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown webhook event type');

        $this->registry->getEventClass('unknown/event');
    }

    public function testMapperParsesProductUpdatePayload(): void
    {
        $mapper = new ShopifyProductUpdatedMapper();
        self::assertSame('products/update', $mapper->getDefinition());

        $payload = [
            'id' => 123456789,
            'title' => 'Example Product',
            'vendor' => 'Example Vendor',
            'tags' => 'tag1, tag2, tag3',
            'updated_at' => '2026-09-18T10:30:00Z',
        ];

        $event = $mapper->map($payload, []);

        self::assertInstanceOf(ShopifyProductUpdated::class, $event);
        self::assertSame('123456789', $event->productId);
        self::assertSame('Example Product', $event->title);
        self::assertSame('Example Vendor', $event->vendor);
        self::assertSame(['tag1', 'tag2', 'tag3'], $event->tags);
        self::assertNotNull($event->updatedAt);
    }

    public function testMapperParsesOrderCreatePayload(): void
    {
        $mapper = new ShopifyOrderCreatedMapper();
        self::assertSame('orders/create', $mapper->getDefinition());

        $payload = [
            'id' => 987654321,
            'order_number' => 1001,
            'email' => 'customer@example.com',
            'customer' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
            ],
            'total_price' => '99.99',
            'currency' => 'USD',
            'line_items' => [
                [
                    'id' => 1,
                    'title' => 'Product A',
                    'quantity' => 2,
                    'price' => '25.00',
                ],
                [
                    'id' => 2,
                    'title' => 'Product B',
                    'quantity' => 1,
                    'price' => '49.99',
                ],
            ],
            'created_at' => '2026-09-18T11:00:00Z',
        ];

        $event = $mapper->map($payload, []);

        self::assertInstanceOf(ShopifyOrderCreated::class, $event);
        self::assertSame('987654321', $event->orderId);
        self::assertSame('1001', $event->orderNumber);
        self::assertSame('customer@example.com', $event->customerEmail);
        self::assertSame('John Doe', $event->customerName);
        self::assertSame(99.99, $event->totalPrice);
        self::assertSame('USD', $event->currency);
        self::assertCount(2, $event->lineItems);
        self::assertSame('Product A', $event->lineItems[0]['title']);
        self::assertSame(2, $event->lineItems[0]['quantity']);
        self::assertNotNull($event->createdAt);
    }

    public function testMapperParsesCustomerUpdatePayload(): void
    {
        $mapper = new ShopifyCustomerUpdatedMapper();
        self::assertSame('customers/update', $mapper->getDefinition());

        $payload = [
            'id' => 555666777,
            'email' => 'john@example.com',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone' => '+1234567890',
            'verified_email' => true,
            'updated_at' => '2026-09-18T09:45:00Z',
        ];

        $event = $mapper->map($payload, []);

        self::assertInstanceOf(ShopifyCustomerUpdated::class, $event);
        self::assertSame('555666777', $event->customerId);
        self::assertSame('john@example.com', $event->email);
        self::assertSame('John', $event->firstName);
        self::assertSame('Smith', $event->lastName);
        self::assertSame('+1234567890', $event->phone);
        self::assertTrue($event->isVerified);
        self::assertNotNull($event->updatedAt);
    }

    public function testMapperParsesInventoryUpdatePayload(): void
    {
        $mapper = new ShopifyInventoryUpdatedMapper();
        self::assertSame('inventory_levels/update', $mapper->getDefinition());

        $payload = [
            'inventory_item_id' => 111222333,
            'location_id' => 444555666,
            'available_quantity' => 42,
            'updated_at' => '2026-09-18T08:20:00Z',
        ];

        $event = $mapper->map($payload, []);

        self::assertInstanceOf(ShopifyInventoryUpdated::class, $event);
        self::assertSame('111222333', $event->inventoryItemId);
        self::assertSame('444555666', $event->locationId);
        self::assertSame(42, $event->availableQuantity);
        self::assertNotNull($event->updatedAt);
    }

    public function testOrderMapperHandlesMinimalPayload(): void
    {
        $mapper = new ShopifyOrderCreatedMapper();

        // Minimal payload without customer, line items, etc.
        $payload = [
            'id' => 123,
            'order_number' => 1,
            'email' => 'minimal@test.com',
            'total_price' => '10.00',
            'currency' => 'USD',
        ];

        $event = $mapper->map($payload, []);

        self::assertInstanceOf(ShopifyOrderCreated::class, $event);
        self::assertSame('123', $event->orderId);
        self::assertNull($event->customerName);
        self::assertEmpty($event->lineItems);
        self::assertNull($event->createdAt);
    }

    public function testProductMapperHandlesTagsAsEmptyString(): void
    {
        $mapper = new ShopifyProductUpdatedMapper();

        $payload = [
            'id' => 789,
            'title' => 'Tagless Product',
            'tags' => '',
        ];

        $event = $mapper->map($payload, []);

        self::assertInstanceOf(ShopifyProductUpdated::class, $event);
        self::assertEmpty($event->tags);
    }

    public function testEventSerializability(): void
    {
        $mapper = new ShopifyProductUpdatedMapper();
        $payload = [
            'id' => 999,
            'title' => 'Serializable Product',
            'vendor' => 'Test Vendor',
            'tags' => 'test',
        ];

        $event = $mapper->map($payload, []);

        // Serialize and unserialize
        $serialized = serialize($event);
        $unserialized = unserialize($serialized);

        self::assertInstanceOf(ShopifyProductUpdated::class, $unserialized);
        self::assertSame('999', $unserialized->productId);
        self::assertSame('Serializable Product', $unserialized->title);
    }
}
