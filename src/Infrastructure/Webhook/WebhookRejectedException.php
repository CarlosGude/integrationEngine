<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook;

use IntegrationEngine\Core\Exception\WebhookRejectionReason;

final class WebhookRejectedException extends \RuntimeException
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
