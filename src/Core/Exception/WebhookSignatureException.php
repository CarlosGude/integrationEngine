<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Exception;

final class WebhookSignatureException extends \RuntimeException
{
    public function __construct(private readonly WebhookRejectionReason $rejectionReason)
    {
        parent::__construct('Webhook rejected: '.$rejectionReason->value);
    }

    public function reason(): WebhookRejectionReason
    {
        return $this->rejectionReason;
    }
}
