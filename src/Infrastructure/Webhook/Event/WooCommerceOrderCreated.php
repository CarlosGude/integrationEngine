<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook\Event;

use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;

/**
 * WooCommerce order.created webhook event.
 *
 * Represents a new order in WooCommerce.
 *
 * @author Carlos Gude
 */
final readonly class WooCommerceOrderCreated implements WebhookEventInterface
{
    /**
     * @param int                     $orderId       Order ID
     * @param string                  $orderNumber   Order number (sequential)
     * @param string                  $customerEmail Customer email
     * @param null|string             $customerName  Customer name
     * @param float                   $total         Order total (including tax/shipping)
     * @param string                  $currency      Currency code (USD, EUR, etc.)
     * @param string                  $status        Order status (pending, processing, completed, etc.)
     * @param null|\DateTimeImmutable $createdAt     When the order was created
     */
    public function __construct(
        public readonly int $orderId,
        public readonly string $orderNumber,
        public readonly string $customerEmail,
        public readonly ?string $customerName,
        public readonly float $total,
        public readonly string $currency,
        public readonly string $status,
        public readonly ?\DateTimeImmutable $createdAt,
    ) {}
}
