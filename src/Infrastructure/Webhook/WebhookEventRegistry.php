<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook;

use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;

/**
 * Registry mapping webhook event types to their DTO classes.
 *
 * Enables dynamic, platform-agnostic lookup of which DTO class to instantiate
 * for a given webhook event type (e.g., 'products/update' → ShopifyProductUpdated).
 *
 * @author Carlos Gude
 */
final class WebhookEventRegistry
{
    /** @var array<string, class-string<WebhookEventInterface>> */
    private array $registry = [];

    /**
     * Register an event type → DTO class mapping.
     *
     * @param string                              $eventType Event type identifier (e.g., 'products/update')
     * @param class-string<WebhookEventInterface> $dtoClass  Fully qualified DTO class name
     */
    public function register(string $eventType, string $dtoClass): void
    {
        $this->registry[$eventType] = $dtoClass;
    }

    /**
     * Look up the DTO class for an event type.
     *
     * @return class-string<WebhookEventInterface> The DTO class name
     *
     * @throws \InvalidArgumentException If event type is not registered
     */
    public function getEventClass(string $eventType): string
    {
        if (!isset($this->registry[$eventType])) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'Unknown webhook event type "%s". Registered types: %s',
                    $eventType,
                    implode(', ', array_keys($this->registry)),
                ),
            );
        }

        return $this->registry[$eventType];
    }

    /**
     * Check if an event type is registered.
     */
    public function has(string $eventType): bool
    {
        return isset($this->registry[$eventType]);
    }

    /**
     * Get all registered event types.
     *
     * @return array<string>
     */
    public function listEventTypes(): array
    {
        return array_keys($this->registry);
    }
}
