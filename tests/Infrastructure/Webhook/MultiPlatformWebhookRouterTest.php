<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Webhook;

use IntegrationEngine\Core\Webhook\ShopifyHmacSignatureVerifier;
use IntegrationEngine\Core\Webhook\WebhookPlatform;
use IntegrationEngine\Core\Webhook\WebhookPlatformConfig;
use IntegrationEngine\Core\Webhook\WooCommerceHmacSignatureVerifier;
use IntegrationEngine\Infrastructure\Webhook\Event\ShopifyOrderCreated;
use IntegrationEngine\Infrastructure\Webhook\Event\ShopifyProductUpdated;
use IntegrationEngine\Infrastructure\Webhook\Event\WooCommerceOrderCreated;
use IntegrationEngine\Infrastructure\Webhook\Event\WooCommerceProductUpdated;
use IntegrationEngine\Infrastructure\Webhook\WebhookEventRegistry;
use IntegrationEngine\Infrastructure\Webhook\WebhookPlatformRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Tests for multi-platform webhook platform registry and routing.
 *
 * @author Carlos Gude
 */
final class MultiPlatformWebhookRouterTest extends TestCase
{
    private WebhookPlatformRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new WebhookPlatformRegistry();

        // Register Shopify
        $shopifyRegistry = new WebhookEventRegistry();
        $shopifyRegistry->register('products/update', ShopifyProductUpdated::class);
        $shopifyRegistry->register('orders/create', ShopifyOrderCreated::class);

        $shopifyConfig = new WebhookPlatformConfig(
            platform: WebhookPlatform::SHOPIFY,
            verifier: new ShopifyHmacSignatureVerifier(),
            eventRegistry: $shopifyRegistry,
            supportedPaths: ['/webhooks/shopify'],
        );
        $this->registry->register($shopifyConfig);

        // Register WooCommerce
        $wooRegistry = new WebhookEventRegistry();
        $wooRegistry->register('product.updated', WooCommerceProductUpdated::class);
        $wooRegistry->register('order.created', WooCommerceOrderCreated::class);

        $wooConfig = new WebhookPlatformConfig(
            platform: WebhookPlatform::WOOCOMMERCE,
            verifier: new WooCommerceHmacSignatureVerifier(),
            eventRegistry: $wooRegistry,
            supportedPaths: ['/webhooks/woocommerce'],
        );
        $this->registry->register($wooConfig);
    }

    public function testDetectPlatformByPath(): void
    {
        $shopifyConfig = $this->registry->getByPath('/webhooks/shopify');
        self::assertSame(WebhookPlatform::SHOPIFY, $shopifyConfig->platform);

        $wooConfig = $this->registry->getByPath('/webhooks/woocommerce');
        self::assertSame(WebhookPlatform::WOOCOMMERCE, $wooConfig->platform);
    }

    public function testDetectPlatformByHeader(): void
    {
        $shopifyConfig = $this->registry->detectPlatform('shopify', '/some/path');
        self::assertSame(WebhookPlatform::SHOPIFY, $shopifyConfig->platform);

        $wooConfig = $this->registry->detectPlatform('woocommerce', '/some/path');
        self::assertSame(WebhookPlatform::WOOCOMMERCE, $wooConfig->platform);
    }

    public function testDetectPlatformFallbackToPath(): void
    {
        $config = $this->registry->detectPlatform(null, '/webhooks/shopify');
        self::assertSame(WebhookPlatform::SHOPIFY, $config->platform);
    }

    public function testDetectPlatformThrowsOnUnregisteredPath(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('No webhook platform registered for path /unknown');

        $this->registry->getByPath('/unknown');
    }

    public function testDetectPlatformThrowsOnUnregisteredPlatform(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Webhook platform shopify not registered');

        $newRegistry = new WebhookPlatformRegistry();
        $newRegistry->getByPlatform(WebhookPlatform::SHOPIFY);
    }

    public function testShopifyVerifierUsedForShopifyPlatform(): void
    {
        $config = $this->registry->getByPlatform(WebhookPlatform::SHOPIFY);

        self::assertInstanceOf(ShopifyHmacSignatureVerifier::class, $config->verifier);
        self::assertSame('X-Shopify-Hmac-SHA256', $config->verifier->getHeaderName());
    }

    public function testWooCommerceVerifierUsedForWooCommercePlatform(): void
    {
        $config = $this->registry->getByPlatform(WebhookPlatform::WOOCOMMERCE);

        self::assertInstanceOf(WooCommerceHmacSignatureVerifier::class, $config->verifier);
        self::assertSame('X-WC-Webhook-Signature', $config->verifier->getHeaderName());
    }

    public function testEventRegistryIsSpecificToEachPlatform(): void
    {
        $shopifyConfig = $this->registry->getByPlatform(WebhookPlatform::SHOPIFY);
        $wooConfig = $this->registry->getByPlatform(WebhookPlatform::WOOCOMMERCE);

        // Shopify has products/update, WooCommerce does not
        self::assertTrue($shopifyConfig->eventRegistry->has('products/update'));
        self::assertFalse($wooConfig->eventRegistry->has('products/update'));

        // WooCommerce has product.updated, Shopify does not
        self::assertTrue($wooConfig->eventRegistry->has('product.updated'));
        self::assertFalse($shopifyConfig->eventRegistry->has('product.updated'));
    }

    public function testListAllRegisteredPlatforms(): void
    {
        $platforms = $this->registry->listPlatforms();

        self::assertCount(2, $platforms);
        self::assertContains(WebhookPlatform::SHOPIFY, $platforms);
        self::assertContains(WebhookPlatform::WOOCOMMERCE, $platforms);
    }

    public function testHasReturnsTrueForRegisteredPlatforms(): void
    {
        self::assertTrue($this->registry->has(WebhookPlatform::SHOPIFY));
        self::assertTrue($this->registry->has(WebhookPlatform::WOOCOMMERCE));
    }

    public function testHasReturnsFalseForUnregisteredPlatforms(): void
    {
        $newPlatform = WebhookPlatform::from('shopify'); // Create any platform

        // Create another registry without this platform
        $emptyRegistry = new WebhookPlatformRegistry();
        self::assertFalse($emptyRegistry->has($newPlatform));
    }
}
