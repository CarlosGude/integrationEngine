<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook\Parser;

use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\SignatureVerifierInterface;
use IntegrationEngine\Core\Webhook\ShopifyHmacSignatureVerifier;
use IntegrationEngine\Infrastructure\Webhook\IntegrationWebhookRequestParser;
use IntegrationEngine\Infrastructure\Webhook\Mapper\ShopifyOrderCreatedMapper;

/**
 * Parses Shopify orders/create webhook requests.
 *
 * Uses HMAC-SHA256 verification with Shopify's X-Shopify-Hmac-SHA256 header.
 * Shopify signatures are base64-encoded, not hex.
 *
 * @author Carlos Gude
 */
final class ShopifyOrderCreatedParser extends IntegrationWebhookRequestParser
{
    public function __construct(
        private string $webhookSecret,
    ) {}

    public function getDefinition(): string
    {
        return 'orders/create';
    }

    public function getMapper(): AbstractWebhookMapper
    {
        return new ShopifyOrderCreatedMapper();
    }

    protected function getSignatureVerifier(): SignatureVerifierInterface
    {
        return new ShopifyHmacSignatureVerifier();
    }

    protected function getSignatureSecret(): string
    {
        return $this->webhookSecret;
    }
}
