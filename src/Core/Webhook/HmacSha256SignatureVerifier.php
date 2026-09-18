<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Webhook;

use IntegrationEngine\Core\Contract\Webhook\SignatureVerifierInterface;

/**
 * HMAC-SHA256 signature verifier for webhook requests.
 *
 * Verifies signatures in the format: `{prefix}{hash}`
 * where prefix is configurable (e.g., "sha256=") and hash is the HMAC-SHA256
 * of the raw body computed with the shared secret.
 *
 * @author Carlos Gude
 */
final readonly class HmacSha256SignatureVerifier implements SignatureVerifierInterface
{
    public function __construct(
        private string $headerName,
        private string $signaturePrefix,
    ) {}

    public function verify(string $body, string $signature, string $secret): bool
    {
        if ('' === $this->signaturePrefix) {
            $expectedPrefix = 'sha256=';
        } else {
            $expectedPrefix = $this->signaturePrefix;
        }

        if (!str_starts_with($signature, $expectedPrefix)) {
            return false;
        }

        $prefixLen = \strlen($expectedPrefix);
        if (\strlen($signature) <= $prefixLen) {
            return false;
        }

        $hash = substr($signature, $prefixLen);
        $expectedHash = hash_hmac('sha256', $body, $secret);

        return hash_equals($expectedHash, $hash);
    }

    public function getHeaderName(): string
    {
        return $this->headerName;
    }
}
