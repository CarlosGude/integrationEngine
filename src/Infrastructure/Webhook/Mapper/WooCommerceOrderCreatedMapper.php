<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook\Mapper;

use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;
use IntegrationEngine\Infrastructure\Webhook\Event\WooCommerceOrderCreated;

/**
 * Maps WooCommerce order.created webhook payloads to WooCommerceOrderCreated events.
 *
 * @author Carlos Gude
 */
final class WooCommerceOrderCreatedMapper extends AbstractWebhookMapper
{
    public function getDefinition(): string
    {
        return 'order.created';
    }

    public function map(array $payload, array $headers): WebhookEventInterface
    {
        /** @var array{
         *   id: int,
         *   number: string,
         *   billing?: array{email?: string, first_name?: string, last_name?: string},
         *   total: string,
         *   currency: string,
         *   status: string,
         *   date_created?: string,
         * } $payload
         */
        $customerEmail = isset($payload['billing']['email']) ? $payload['billing']['email'] : '';
        $customerName = null;

        if (isset($payload['billing'])) {
            $parts = [];
            if (!empty($payload['billing']['first_name'])) {
                $parts[] = $payload['billing']['first_name'];
            }
            if (!empty($payload['billing']['last_name'])) {
                $parts[] = $payload['billing']['last_name'];
            }
            if ($parts) {
                $customerName = implode(' ', $parts);
            }
        }

        $createdAt = null;
        if (!empty($payload['date_created'])) {
            try {
                $createdAt = new \DateTimeImmutable($payload['date_created']);
            } catch (\Exception) {
                // Unparseable date: leave it null.
            }
        }

        return new WooCommerceOrderCreated(
            orderId: $payload['id'],
            orderNumber: $payload['number'],
            customerEmail: $customerEmail,
            customerName: $customerName,
            total: (float) $payload['total'],
            currency: $payload['currency'],
            status: $payload['status'],
            createdAt: $createdAt,
        );
    }
}
