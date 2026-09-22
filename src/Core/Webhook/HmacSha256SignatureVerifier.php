<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Webhook;

use IntegrationEngine\Core\Contract\Webhook\SignatureConfig;
use IntegrationEngine\Core\Contract\Webhook\SignatureVerifierInterface;
use IntegrationEngine\Core\Exception\WebhookRejectionReason;
use IntegrationEngine\Core\Exception\WebhookSignatureException;

final readonly class HmacSha256SignatureVerifier implements SignatureVerifierInterface
{
    public function verify(string $rawBody, array $headers, SignatureConfig $config): void
    {
        $signature = SignatureHeader::read($headers, $config);
        $prefix = $config->prefix ?? '';
        if (!str_starts_with($signature, $prefix)) {
            throw new WebhookSignatureException(WebhookRejectionReason::HeaderMalformed);
        }
        $hash = substr($signature, \strlen($prefix));
        if (1 !== preg_match('/^[a-fA-F0-9]{64}$/D', $hash)) {
            throw new WebhookSignatureException(WebhookRejectionReason::HeaderMalformed);
        }
        if (!hash_equals(hash_hmac('sha256', $rawBody, $config->secret), strtolower($hash))) {
            throw new WebhookSignatureException(WebhookRejectionReason::SignatureInvalid);
        }
    }
}
