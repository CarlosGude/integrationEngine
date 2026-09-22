<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Webhook;

enum UnknownEventPolicy: string
{
    case Ignore = 'ignore';
    case Reject = 'reject';
}
