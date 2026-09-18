<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Webhook;

use IntegrationEngine\Infrastructure\Webhook\Controller\ShopifyWebhookController;
use IntegrationEngine\Infrastructure\Webhook\Event\ShopifyOrderCreated;
use IntegrationEngine\Infrastructure\Webhook\Event\ShopifyProductUpdated;
use IntegrationEngine\Infrastructure\Webhook\WebhookEventDispatcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Functional tests for Shopify webhook ingestion endpoint.
 *
 * Tests the complete flow: HTTP request → parser → dispatcher → domain event.
 *
 * @author Carlos Gude
 */
final class ShopifyWebhookIngestionsTest extends TestCase
{
    private const WEBHOOK_SECRET = 'test_shopify_secret';

    private EventDispatcher $eventDispatcher;
    private WebhookEventDispatcher $webhookDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->webhookDispatcher = new WebhookEventDispatcher($this->eventDispatcher);
    }

    public function testProductUpdateWebhookDispatchesEvent(): void
    {
        $payload = [
            'id' => 123456789,
            'title' => 'Updated Product',
            'vendor' => 'Acme Corp',
            'tags' => 'sale, featured',
            'updated_at' => '2026-09-18T10:30:00Z',
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = $this->generateShopifySignature($body);

        $request = Request::create(
            uri: '/webhooks/shopify',
            method: 'POST',
            server: [
                'HTTP_X_SHOPIFY_TOPIC' => 'products/update',
                'HTTP_X_SHOPIFY_HMAC_SHA256' => $signature,
            ],
            content: $body,
        );

        // Assert event was dispatched
        $eventCaught = false;
        $this->eventDispatcher->addListener(ShopifyProductUpdated::class, static function (ShopifyProductUpdated $event) use (&$eventCaught): void {
            $eventCaught = true;
            self::assertSame('123456789', $event->productId);
            self::assertSame('Updated Product', $event->title);
        });

        $controller = $this->createController();
        $response = $controller->handleShopifyWebhook($request);

        self::assertTrue($eventCaught, 'Domain event was not dispatched');
        self::assertSame(204, $response->getStatusCode());
    }

    public function testOrderCreateWebhookDispatchesEvent(): void
    {
        $payload = [
            'id' => 987654321,
            'order_number' => 1001,
            'email' => 'customer@example.com',
            'customer' => [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
            ],
            'total_price' => '129.99',
            'currency' => 'USD',
            'line_items' => [
                [
                    'id' => 1,
                    'title' => 'Widget A',
                    'quantity' => 1,
                    'price' => '99.99',
                ],
            ],
            'created_at' => '2026-09-18T11:00:00Z',
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = $this->generateShopifySignature($body);

        $request = Request::create(
            uri: '/webhooks/shopify',
            method: 'POST',
            server: [
                'HTTP_X_SHOPIFY_TOPIC' => 'orders/create',
                'HTTP_X_SHOPIFY_HMAC_SHA256' => $signature,
            ],
            content: $body,
        );

        // Assert event was dispatched
        $eventCaught = false;
        $this->eventDispatcher->addListener(ShopifyOrderCreated::class, static function (ShopifyOrderCreated $event) use (&$eventCaught): void {
            $eventCaught = true;
            self::assertSame('987654321', $event->orderId);
            self::assertSame('customer@example.com', $event->customerEmail);
            self::assertSame('Jane Doe', $event->customerName);
        });

        $controller = $this->createController();
        $response = $controller->handleShopifyWebhook($request);

        self::assertTrue($eventCaught, 'Domain event was not dispatched');
        self::assertSame(204, $response->getStatusCode());
    }

    public function testMissingTopicHeaderThrowsException(): void
    {
        $payload = ['id' => 123, 'title' => 'Test'];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = $this->generateShopifySignature($body);

        $request = Request::create(
            uri: '/webhooks/shopify',
            method: 'POST',
            server: [
                'HTTP_X_SHOPIFY_HMAC_SHA256' => $signature,
            ],
            content: $body,
        );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Missing X-Shopify-Topic header');

        $controller = $this->createController();
        $controller->handleShopifyWebhook($request);
    }

    public function testInvalidSignatureThrowsException(): void
    {
        $payload = ['id' => 123, 'title' => 'Test'];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $request = Request::create(
            uri: '/webhooks/shopify',
            method: 'POST',
            server: [
                'HTTP_X_SHOPIFY_TOPIC' => 'products/update',
                'HTTP_X_SHOPIFY_HMAC_SHA256' => 'invalid_signature',
            ],
            content: $body,
        );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Signature verification failed');

        $controller = $this->createController();
        $controller->handleShopifyWebhook($request);
    }

    public function testUnknownTopicReturnsNoContent(): void
    {
        $payload = ['id' => 123];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = $this->generateShopifySignature($body);

        $request = Request::create(
            uri: '/webhooks/shopify',
            method: 'POST',
            server: [
                'HTTP_X_SHOPIFY_TOPIC' => 'unknown/event',
                'HTTP_X_SHOPIFY_HMAC_SHA256' => $signature,
            ],
            content: $body,
        );

        $controller = $this->createController();
        $response = $controller->handleShopifyWebhook($request);

        self::assertSame(204, $response->getStatusCode());
    }

    private function generateShopifySignature(string $body): string
    {
        // Shopify signature: base64(hmac_sha256(body, secret))
        $hash = hash_hmac('sha256', $body, self::WEBHOOK_SECRET, true);

        return base64_encode($hash);
    }

    private function createController(): ShopifyWebhookController
    {
        return new ShopifyWebhookController(
            $this->webhookDispatcher,
            self::WEBHOOK_SECRET,
        );
    }
}
