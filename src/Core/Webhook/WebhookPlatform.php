<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Webhook;

/**
 * Webhook platform enumeration.
 *
 * Supported webhook platforms (Shopify, WooCommerce, etc.).
 *
 * @author Carlos Gude
 */
enum WebhookPlatform: string
{
    case SHOPIFY = 'shopify';
    case WOOCOMMERCE = 'woocommerce';

    public function label(): string
    {
        return match ($this) {
            self::SHOPIFY => 'Shopify',
            self::WOOCOMMERCE => 'WooCommerce',
        };
    }
}
