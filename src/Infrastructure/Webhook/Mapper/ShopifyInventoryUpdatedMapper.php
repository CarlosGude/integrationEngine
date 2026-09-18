<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook\Mapper;

use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;
use IntegrationEngine\Infrastructure\Webhook\Event\ShopifyInventoryUpdated;

/**
 * Maps Shopify inventory_levels/update webhook payloads to ShopifyInventoryUpdated events.
 *
 * @author Carlos Gude
 */
final class ShopifyInventoryUpdatedMapper extends AbstractWebhookMapper
{
    public function getDefinition(): string
    {
        return 'inventory_levels/update';
    }

    public function map(array $payload, array $headers): WebhookEventInterface
    {
        /** @var array{
         *   inventory_item_id: int|string,
         *   location_id: int|string,
         *   available_quantity: int|string,
         *   updated_at?: string,
         * } $payload
         */
        $updatedAt = null;
        if (!empty($payload['updated_at'])) {
            try {
                $updatedAt = new \DateTimeImmutable($payload['updated_at']);
            } catch (\Exception) {
            }
        }

        return new ShopifyInventoryUpdated(
            inventoryItemId: (string) $payload['inventory_item_id'],
            locationId: (string) $payload['location_id'],
            availableQuantity: (int) $payload['available_quantity'],
            updatedAt: $updatedAt,
        );
    }
}
