<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Webhook;

use IntegrationEngine\Core\Contract\Webhook\SignatureConfig;
use IntegrationEngine\Core\Exception\WebhookRejectionReason;
use IntegrationEngine\Core\Exception\WebhookSignatureException;

final class SignatureHeader
{
    /** @param array<string, list<string>> $headers */
    public static function read(array $headers, SignatureConfig $config): string
    {
        $values = [];
        foreach ($headers as $name => $headerValues) {
            if (0 === strcasecmp($name, $config->header)) {
                $values = [...$values, ...$headerValues];
            }
        }
        if ([] === $values) {
            throw new WebhookSignatureException(WebhookRejectionReason::HeaderMissing);
        }
        if (1 !== \count($values) || '' === $values[0]) {
            throw new WebhookSignatureException(WebhookRejectionReason::HeaderMalformed);
        }

        return $values[0];
    }
}
