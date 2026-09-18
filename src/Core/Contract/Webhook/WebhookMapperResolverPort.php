<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Webhook;

/**
 * Port for resolving webhook mappers by event type.
 *
 * Implementations maintain the mapping of event types to their corresponding mappers.
 *
 * @author Carlos Gude
 */
interface WebhookMapperResolverPort
{
    /**
     * Resolve a mapper for a webhook event type.
     *
     * @param string $eventType Event type (e.g., 'products/update')
     *
     * @return null|AbstractWebhookMapper The mapper, or null if event type is unknown
     */
    public function resolveMapper(string $eventType): ?AbstractWebhookMapper;
}
