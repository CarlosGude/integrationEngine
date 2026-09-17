<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Webhook;

/**
 * Verifies webhook request signatures from external providers.
 *
 * @author Carlos Gude
 */
interface SignatureVerifierInterface
{
    /**
     * Verify the signature of a webhook request.
     *
     * @param string $body      The raw request body
     * @param string $signature The signature from the request (e.g., from header)
     * @param string $secret    The shared secret for verification
     *
     * @return bool True if signature is valid, false otherwise
     */
    public function verify(string $body, string $signature, string $secret): bool;

    /**
     * Get the HTTP header name where this verifier expects the signature.
     *
     * @return string (e.g., 'X-Signature', 'Stripe-Signature')
     */
    public function getHeaderName(): string;
}
