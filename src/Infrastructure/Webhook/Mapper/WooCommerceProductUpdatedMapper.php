<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook\Mapper;

use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;
use IntegrationEngine\Infrastructure\Webhook\Event\WooCommerceProductUpdated;

/**
 * Maps WooCommerce product.updated webhook payloads to WooCommerceProductUpdated events.
 *
 * @author Carlos Gude
 */
final class WooCommerceProductUpdatedMapper extends AbstractWebhookMapper
{
    public function getDefinition(): string
    {
        return 'product.updated';
    }

    public function map(array $payload, array $headers): WebhookEventInterface
    {
        /** @var array{
         *   id: int,
         *   name: string,
         *   regular_price?: string,
         *   stock_quantity?: int,
         *   status?: string,
         *   date_modified?: string,
         * } $payload
         */
        $updatedAt = null;
        if (!empty($payload['date_modified'])) {
            try {
                $updatedAt = new \DateTimeImmutable($payload['date_modified']);
            } catch (\Exception) {
            }
        }

        return new WooCommerceProductUpdated(
            productId: $payload['id'],
            title: $payload['name'],
            price: isset($payload['regular_price']) ? (float) $payload['regular_price'] : null,
            stockQty: isset($payload['stock_quantity']) ? (int) $payload['stock_quantity'] : null,
            status: $payload['status'] ?? 'publish',
            updatedAt: $updatedAt,
        );
    }
}
