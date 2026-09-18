<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook\Mapper;

use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;
use IntegrationEngine\Infrastructure\Webhook\Event\ShopifyOrderCreated;

/**
 * Maps Shopify orders/create webhook payloads to ShopifyOrderCreated events.
 *
 * @author Carlos Gude
 */
final class ShopifyOrderCreatedMapper extends AbstractWebhookMapper
{
    public function getDefinition(): string
    {
        return 'orders/create';
    }

    public function map(array $payload, array $headers): WebhookEventInterface
    {
        /** @var array{
         *   id: int|string,
         *   order_number: int|string,
         *   email: string,
         *   customer?: null|array{first_name?: string, last_name?: string},
         *   total_price: float|string,
         *   currency: string,
         *   line_items?: array<int, array{id?: int|string, title?: string, quantity?: int, price?: float|string}>,
         *   created_at?: string,
         * } $payload
         */
        $customerEmail = $payload['email'];
        $customerName = null;
        if (!empty($payload['customer'])) {
            $parts = [];
            if (!empty($payload['customer']['first_name'])) {
                $parts[] = $payload['customer']['first_name'];
            }
            if (!empty($payload['customer']['last_name'])) {
                $parts[] = $payload['customer']['last_name'];
            }
            if ($parts) {
                $customerName = implode(' ', $parts);
            }
        }

        $lineItems = [];
        if (!empty($payload['line_items'])) {
            foreach ($payload['line_items'] as $item) {
                $lineItems[] = [
                    'id' => (string) ($item['id'] ?? ''),
                    'title' => $item['title'] ?? '',
                    'quantity' => (int) ($item['quantity'] ?? 0),
                    'price' => (float) ($item['price'] ?? 0),
                ];
            }
        }

        $createdAt = null;
        if (!empty($payload['created_at'])) {
            try {
                $createdAt = new \DateTimeImmutable($payload['created_at']);
            } catch (\Exception) {

            }
        }

        return new ShopifyOrderCreated(
            orderId: (string) $payload['id'],
            orderNumber: (string) $payload['order_number'],
            customerEmail: $customerEmail,
            customerName: $customerName,
            totalPrice: (float) $payload['total_price'],
            currency: $payload['currency'],
            lineItems: $lineItems,
            createdAt: $createdAt,
        );
    }
}
