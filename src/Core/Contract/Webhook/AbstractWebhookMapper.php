<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Webhook;

use IntegrationEngine\Core\Exception\WebhookMapperMismatchException;

abstract class AbstractWebhookMapper
{
    abstract public static function eventType(): string;

    /**
     * @param array<mixed>                $payload
     * @param array<string, list<string>> $headers
     */
    final public static function map(string $eventType, array $payload, array $headers): WebhookEventInterface
    {
        if ($eventType !== static::eventType()) {
            throw WebhookMapperMismatchException::for(static::class, $eventType);
        }

        return static::transform($payload, $headers);
    }

    /**
     * @param array<mixed>                $payload
     * @param array<string, list<string>> $headers
     */
    abstract protected static function transform(array $payload, array $headers): WebhookEventInterface;
}
