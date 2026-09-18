<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook\Mapper;

use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;
use IntegrationEngine\Infrastructure\Webhook\Event\ShopifyCustomerUpdated;

/**
 * Maps Shopify customers/update webhook payloads to ShopifyCustomerUpdated events.
 *
 * @author Carlos Gude
 */
final class ShopifyCustomerUpdatedMapper extends AbstractWebhookMapper
{
    public function getDefinition(): string
    {
        return 'customers/update';
    }

    public function map(array $payload, array $headers): WebhookEventInterface
    {
        /** @var array{
         *   id: int|string,
         *   email: string,
         *   first_name?: null|string,
         *   last_name?: null|string,
         *   phone?: null|string,
         *   verified_email?: bool,
         *   updated_at?: string,
         * } $payload
         */
        $updatedAt = null;
        if (!empty($payload['updated_at'])) {
            try {
                $updatedAt = new \DateTimeImmutable($payload['updated_at']);
            } catch (\Exception) {
                // If parsing fails, leave null
            }
        }

        return new ShopifyCustomerUpdated(
            customerId: (string) $payload['id'],
            email: $payload['email'],
            firstName: $payload['first_name'] ?? null,
            lastName: $payload['last_name'] ?? null,
            phone: $payload['phone'] ?? null,
            isVerified: (bool) ($payload['verified_email'] ?? false),
            updatedAt: $updatedAt,
        );
    }
}
