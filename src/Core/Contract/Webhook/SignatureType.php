<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Webhook;

enum SignatureType: string
{
    case HmacSha256 = 'hmac_sha256';
    case TimestampedHmac = 'timestamped_hmac';
    case Base64Hmac = 'hmac_base64';
}
