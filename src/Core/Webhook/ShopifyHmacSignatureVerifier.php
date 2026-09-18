<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Webhook;

use IntegrationEngine\Core\Contract\Webhook\SignatureVerifierInterface;

/**
 * HMAC-SHA256 signature verifier for Shopify webhooks.
 *
 * Shopify signs webhooks with base64-encoded HMAC-SHA256 hashes.
 * The signature header is X-Shopify-Hmac-SHA256.
 *
 * @author Carlos Gude
 */
final readonly class ShopifyHmacSignatureVerifier implements SignatureVerifierInterface
{
    public function __construct(
        private string $headerName = 'X-Shopify-Hmac-SHA256',
    ) {}

    public function verify(string $body, string $signature, string $secret): bool
    {
        $expectedHash = hash_hmac('sha256', $body, $secret, true);
        $expectedSignature = base64_encode($expectedHash);

        return hash_equals($expectedSignature, $signature);
    }

    public function getHeaderName(): string
    {
        return $this->headerName;
    }
}
