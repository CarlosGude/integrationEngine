<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook\Event;

use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;

/**
 * Shopify orders/create webhook event.
 *
 * Represents a new order placed in Shopify.
 *
 * @author Carlos Gude
 */
final readonly class ShopifyOrderCreated implements WebhookEventInterface
{
    /**
     * @param string                           $orderId       Order ID (global)
     * @param string                           $orderNumber   Display order number (e.g., #1001)
     * @param string                           $customerEmail Customer email address
     * @param null|string                      $customerName  Customer name
     * @param float                            $totalPrice    Total order amount (string in Shopify, parsed to float)
     * @param string                           $currency      Order currency code (e.g., USD)
     * @param array<int, array<string, mixed>> $lineItems     Array of line items (id, title, quantity, price)
     * @param null|\DateTimeImmutable          $createdAt     When the order was created
     */
    public function __construct(
        public readonly string $orderId,
        public readonly string $orderNumber,
        public readonly string $customerEmail,
        public readonly ?string $customerName,
        public readonly float $totalPrice,
        public readonly string $currency,
        public readonly array $lineItems,
        public readonly ?\DateTimeImmutable $createdAt,
    ) {}
}
