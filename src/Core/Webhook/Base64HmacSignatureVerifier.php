<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Webhook;

use IntegrationEngine\Core\Contract\Webhook\SignatureVerifierInterface;

/**
 * Verifies a raw HMAC-SHA256 of the body, base64-encoded and sent whole:
 * no prefix, no separators.
 *
 * The other common shape is the hex digest behind a prefix
 * (`sha256=<hex>`), which HmacSha256SignatureVerifier handles.
 *
 * @author Carlos Gude
 */
final readonly class Base64HmacSignatureVerifier implements SignatureVerifierInterface
{
    public function __construct(
        private string $headerName,
    ) {}

    public function verify(string $body, string $signature, string $secret): bool
    {
        $expectedSignature = base64_encode(hash_hmac('sha256', $body, $secret, true));

        return hash_equals($expectedSignature, $signature);
    }

    public function getHeaderName(): string
    {
        return $this->headerName;
    }
}
