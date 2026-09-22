<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Exception;

final class WebhookMapperMismatchException extends \InvalidArgumentException
{
    /** @param class-string $mapper */
    public static function for(string $mapper, string $eventType): self
    {
        return new self('Webhook mapper '.$mapper.' does not handle the declared event type.');
    }
}
