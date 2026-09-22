<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Exception;

enum WebhookRejectionReason: string
{
    case HeaderMissing = 'header_missing';
    case HeaderMalformed = 'header_malformed';
    case SignatureInvalid = 'signature_invalid';
    case TimestampOutOfTolerance = 'timestamp_out_of_tolerance';
    case PayloadInvalid = 'payload_invalid';
    case UnknownEvent = 'unknown_event';
}
