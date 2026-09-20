<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook\Parser;

use IntegrationEngine\Core\Contract\Webhook\SignatureVerifierInterface;
use IntegrationEngine\Core\Webhook\ShopifyHmacSignatureVerifier;

/**
 * The Shopify half of a request parser: one verifier, always the same.
 *
 * A parser that uses this only has to declare its event type and its mapper.
 * The signing secret comes from framework.webhook.routing.<key>.secret, which
 * Symfony passes to parse(), so the parser needs no constructor either.
 *
 * @author Carlos Gude
 */
trait VerifiesShopifySignature
{
    protected function getSignatureVerifier(): SignatureVerifierInterface
    {
        return new ShopifyHmacSignatureVerifier();
    }
}
