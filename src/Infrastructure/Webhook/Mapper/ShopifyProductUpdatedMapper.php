<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook\Mapper;

use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;
use IntegrationEngine\Infrastructure\Webhook\Event\ShopifyProductUpdated;

/**
 * Maps Shopify products/update webhook payloads to ShopifyProductUpdated events.
 *
 * @author Carlos Gude
 */
final class ShopifyProductUpdatedMapper extends AbstractWebhookMapper
{
    public function getDefinition(): string
    {
        return 'products/update';
    }

    public function map(array $payload, array $headers): WebhookEventInterface
    {
        /** @var array{
         *   id: int|string,
         *   title: string,
         *   vendor?: null|string,
         *   tags?: string,
         *   updated_at?: string,
         * } $payload
         */
        $tags = [];
        if (!empty($payload['tags'])) {
            $tags = array_map('trim', explode(',', $payload['tags']));
        }

        $updatedAt = null;
        if (!empty($payload['updated_at'])) {
            try {
                $updatedAt = new \DateTimeImmutable($payload['updated_at']);
            } catch (\Exception) {
            }
        }

        return new ShopifyProductUpdated(
            productId: (string) $payload['id'],
            title: $payload['title'],
            vendor: $payload['vendor'] ?? null,
            tags: $tags,
            updatedAt: $updatedAt,
        );
    }
}
