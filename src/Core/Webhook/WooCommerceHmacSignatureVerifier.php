<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Webhook;

use IntegrationEngine\Core\Contract\Webhook\SignatureVerifierInterface;

/**
 * HMAC-SHA256 signature verifier for WooCommerce webhooks.
 *
 * WooCommerce signs webhooks with base64-encoded HMAC-SHA256 hashes.
 * The signature header is X-WC-Webhook-Signature.
 *
 * @author Carlos Gude
 */
final readonly class WooCommerceHmacSignatureVerifier implements SignatureVerifierInterface
{
    public function __construct(
        private string $headerName = 'X-WC-Webhook-Signature',
    ) {}

    public function verify(string $body, string $signature, string $secret): bool
    {
        // WooCommerce signature is base64-encoded, compute expected signature
        $expectedHash = hash_hmac('sha256', $body, $secret, true);
        $expectedSignature = base64_encode($expectedHash);

        // Use timing-safe comparison to prevent timing attacks
        return hash_equals($expectedSignature, $signature);
    }

    public function getHeaderName(): string
    {
        return $this->headerName;
    }
}
