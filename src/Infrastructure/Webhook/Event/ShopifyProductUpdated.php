<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook\Event;

use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;

/**
 * Shopify products/update webhook event.
 *
 * Represents a product update from Shopify (title, description, pricing, tags change).
 *
 * @author Carlos Gude
 */
final readonly class ShopifyProductUpdated implements WebhookEventInterface
{
    /**
     * @param string                  $productId Product ID (global)
     * @param string                  $title     Product title
     * @param null|string             $vendor    Product vendor/brand
     * @param list<string>            $tags      Product tags (comma-separated in raw, array here)
     * @param null|\DateTimeImmutable $updatedAt When the product was updated
     */
    public function __construct(
        public readonly string $productId,
        public readonly string $title,
        public readonly ?string $vendor,
        public readonly array $tags,
        public readonly ?\DateTimeImmutable $updatedAt,
    ) {}
}
