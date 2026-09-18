<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook\Event;

use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;

/**
 * WooCommerce product.updated webhook event.
 *
 * Represents a product update in WooCommerce (title, price, stock change).
 *
 * @author Carlos Gude
 */
final readonly class WooCommerceProductUpdated implements WebhookEventInterface
{
    /**
     * @param int                     $productId Product ID
     * @param string                  $title     Product title
     * @param null|float              $price     Product price (regular price)
     * @param null|int                $stockQty  Stock quantity
     * @param string                  $status    Product status (publish, draft, etc.)
     * @param null|\DateTimeImmutable $updatedAt When the product was updated
     */
    public function __construct(
        public readonly int $productId,
        public readonly string $title,
        public readonly ?float $price,
        public readonly ?int $stockQty,
        public readonly string $status,
        public readonly ?\DateTimeImmutable $updatedAt,
    ) {}
}
