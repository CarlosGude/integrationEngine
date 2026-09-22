<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Webhook;

use IntegrationEngine\Core\Contract\Webhook\SignatureConfig;
use IntegrationEngine\Core\Contract\Webhook\SignatureVerifierInterface;
use IntegrationEngine\Core\Exception\WebhookRejectionReason;
use IntegrationEngine\Core\Exception\WebhookSignatureException;

final readonly class Base64HmacSignatureVerifier implements SignatureVerifierInterface
{
    public function verify(string $rawBody, array $headers, SignatureConfig $config): void
    {
        $signature = SignatureHeader::read($headers, $config);
        if (!hash_equals(base64_encode(hash_hmac('sha256', $rawBody, $config->secret, true)), $signature)) {
            throw new WebhookSignatureException(WebhookRejectionReason::SignatureInvalid);
        }
    }
}
