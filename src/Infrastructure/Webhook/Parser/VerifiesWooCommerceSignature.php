<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook\Parser;

use IntegrationEngine\Core\Contract\Webhook\SignatureVerifierInterface;
use IntegrationEngine\Core\Webhook\WooCommerceHmacSignatureVerifier;

/**
 * The WooCommerce half of a request parser: one verifier, always the same.
 *
 * Same deal as VerifiesShopifySignature — declare the event type and the
 * mapper, and let the routing entry carry the secret.
 *
 * @author Carlos Gude
 */
trait VerifiesWooCommerceSignature
{
    protected function getSignatureVerifier(): SignatureVerifierInterface
    {
        return new WooCommerceHmacSignatureVerifier();
    }
}
