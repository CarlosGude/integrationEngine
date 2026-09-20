<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Webhook;

use IntegrationEngine\Core\Webhook\WooCommerceHmacSignatureVerifier;
use IntegrationEngine\Infrastructure\Webhook\Event\WooCommerceOrderCreated;
use IntegrationEngine\Infrastructure\Webhook\Event\WooCommerceProductUpdated;
use IntegrationEngine\Infrastructure\Webhook\Mapper\WooCommerceOrderCreatedMapper;
use IntegrationEngine\Infrastructure\Webhook\Mapper\WooCommerceProductUpdatedMapper;
use PHPUnit\Framework\TestCase;

/**
 * Functional tests for WooCommerce webhook ingestion.
 *
 * Proves that the webhook framework supports multiple platforms
 * (Shopify proven in Day 54, WooCommerce here).
 *
 * @author Carlos Gude
 */
final class WooCommerceWebhookIngestionsTest extends TestCase
{
    private const WEBHOOK_SECRET = 'test_woocommerce_secret';

    private WooCommerceHmacSignatureVerifier $verifier;

    protected function setUp(): void
    {
        $this->verifier = new WooCommerceHmacSignatureVerifier();
    }

    public function testWooCommerceProductUpdatedSignatureValidation(): void
    {
        $mapper = new WooCommerceProductUpdatedMapper();
        self::assertSame('product.updated', $mapper->getDefinition());

        $payload = [
            'id' => 42,
            'name' => 'Cool Product',
            'regular_price' => '29.99',
            'stock_quantity' => 100,
            'status' => 'publish',
            'date_modified' => '2026-09-18T14:30:00Z',
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = $this->generateWooCommerceSignature($body);

        // Verify signature validates
        self::assertTrue($this->verifier->verify($body, $signature, self::WEBHOOK_SECRET));

        // Map payload
        $event = $mapper->map($payload, []);

        self::assertInstanceOf(WooCommerceProductUpdated::class, $event);
        self::assertSame(42, $event->productId);
        self::assertSame('Cool Product', $event->title);
        self::assertSame(29.99, $event->price);
        self::assertSame(100, $event->stockQty);
    }

    public function testWooCommerceOrderCreatedSignatureValidation(): void
    {
        $mapper = new WooCommerceOrderCreatedMapper();
        self::assertSame('order.created', $mapper->getDefinition());

        $payload = [
            'id' => 555,
            'number' => '1234',
            'billing' => [
                'email' => 'customer@woo.com',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
            ],
            'total' => '199.99',
            'currency' => 'USD',
            'status' => 'pending',
            'date_created' => '2026-09-18T15:00:00Z',
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = $this->generateWooCommerceSignature($body);

        // Verify signature validates
        self::assertTrue($this->verifier->verify($body, $signature, self::WEBHOOK_SECRET));

        // Map payload
        $event = $mapper->map($payload, []);

        self::assertInstanceOf(WooCommerceOrderCreated::class, $event);
        self::assertSame(555, $event->orderId);
        self::assertSame('1234', $event->orderNumber);
        self::assertSame('customer@woo.com', $event->customerEmail);
        self::assertSame('Jane Doe', $event->customerName);
        self::assertSame(199.99, $event->total);
    }

    public function testInvalidWooCommerceSignatureRejected(): void
    {
        $payload = ['id' => 42, 'name' => 'Product'];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        // Use wrong signature
        $wrongSignature = base64_encode(hash_hmac('sha256', $body, 'wrong_secret', true));

        self::assertFalse($this->verifier->verify($body, $wrongSignature, self::WEBHOOK_SECRET));
    }

    public function testWooCommerceMapperHandlesMinimalPayload(): void
    {
        $mapper = new WooCommerceProductUpdatedMapper();

        // Minimal payload
        $payload = [
            'id' => 99,
            'name' => 'Minimal Product',
        ];

        $event = $mapper->map($payload, []);

        self::assertInstanceOf(WooCommerceProductUpdated::class, $event);
        self::assertSame(99, $event->productId);
        self::assertNull($event->price);
        self::assertNull($event->stockQty);
        self::assertNull($event->updatedAt);
        // WooCommerce omits the status for a published product.
        self::assertSame('publish', $event->status);
    }

    public function testWooCommerceMapperNormalisesStockAndKeepsTheSentStatus(): void
    {
        $mapper = new WooCommerceProductUpdatedMapper();

        // WooCommerce sends numbers as strings in its REST payloads.
        $event = $mapper->map([
            'id' => 99,
            'name' => 'Draft Product',
            'stock_quantity' => '7',
            'status' => 'draft',
        ], []);

        self::assertInstanceOf(WooCommerceProductUpdated::class, $event);
        self::assertSame(7, $event->stockQty);
        self::assertSame('draft', $event->status);
    }

    public function testWooCommerceOrderMapperHandlesMinimalBilling(): void
    {
        $mapper = new WooCommerceOrderCreatedMapper();

        $payload = [
            'id' => 888,
            'number' => '5678',
            'billing' => [], // Empty billing
            'total' => '50.00',
            'currency' => 'EUR',
            'status' => 'processing',
        ];

        $event = $mapper->map($payload, []);

        self::assertInstanceOf(WooCommerceOrderCreated::class, $event);
        self::assertSame(888, $event->orderId);
        self::assertSame('', $event->customerEmail); // Empty when billing empty
        self::assertNull($event->customerName);
    }

    public function testWooCommerceEventSerialization(): void
    {
        $mapper = new WooCommerceProductUpdatedMapper();
        $payload = [
            'id' => 777,
            'name' => 'Serializable Product',
            'regular_price' => '19.99',
        ];

        $event = $mapper->map($payload, []);

        // Serialize and unserialize
        $serialized = serialize($event);
        $unserialized = unserialize($serialized);

        self::assertInstanceOf(WooCommerceProductUpdated::class, $unserialized);
        self::assertSame(777, $unserialized->productId);
    }

    private function generateWooCommerceSignature(string $body): string
    {
        // WooCommerce signature is base64(hmac_sha256(body, secret))
        $hash = hash_hmac('sha256', $body, self::WEBHOOK_SECRET, true);

        return base64_encode($hash);
    }
}
