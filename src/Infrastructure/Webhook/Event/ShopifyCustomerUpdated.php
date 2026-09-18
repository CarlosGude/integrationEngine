<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook\Event;

use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;

/**
 * Shopify customers/update webhook event.
 *
 * Represents a customer profile update in Shopify (name, email, address change).
 *
 * @author Carlos Gude
 */
final readonly class ShopifyCustomerUpdated implements WebhookEventInterface
{
    /**
     * @param string                  $customerId Customer ID (global)
     * @param string                  $email      Customer email address
     * @param null|string             $firstName  Customer first name
     * @param null|string             $lastName   Customer last name
     * @param null|string             $phone      Customer phone number
     * @param bool                    $isVerified Whether customer email is verified
     * @param null|\DateTimeImmutable $updatedAt  When the customer was updated
     */
    public function __construct(
        public readonly string $customerId,
        public readonly string $email,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly ?string $phone,
        public readonly bool $isVerified,
        public readonly ?\DateTimeImmutable $updatedAt,
    ) {}
}
