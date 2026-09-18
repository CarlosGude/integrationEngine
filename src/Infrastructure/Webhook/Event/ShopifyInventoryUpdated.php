<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook\Event;

use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;

/**
 * Shopify inventory_levels/update webhook event.
 *
 * Represents an inventory level change for a product variant.
 *
 * @author Carlos Gude
 */
final readonly class ShopifyInventoryUpdated implements WebhookEventInterface
{
    /**
     * @param string                  $inventoryItemId   Inventory item ID
     * @param string                  $locationId        Location ID
     * @param int                     $availableQuantity Available quantity at location
     * @param null|\DateTimeImmutable $updatedAt         When the inventory was updated
     */
    public function __construct(
        public readonly string $inventoryItemId,
        public readonly string $locationId,
        public readonly int $availableQuantity,
        public readonly ?\DateTimeImmutable $updatedAt,
    ) {}
}
