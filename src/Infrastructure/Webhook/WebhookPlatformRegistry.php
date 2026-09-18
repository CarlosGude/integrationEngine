<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook;

use IntegrationEngine\Core\Webhook\WebhookPlatform;
use IntegrationEngine\Core\Webhook\WebhookPlatformConfig;

/**
 * Registry of configured webhook platforms.
 *
 * Manages platform configurations (Shopify, WooCommerce, etc.)
 * and provides lookup by platform enum or path.
 *
 * @author Carlos Gude
 */
final class WebhookPlatformRegistry
{
    /** @var array<string, WebhookPlatformConfig> */
    private array $platforms = [];

    public function register(WebhookPlatformConfig $config): void
    {
        $this->platforms[$config->platform->value] = $config;
    }

    /**
     * Find platform config by enum.
     *
     * @throws \DomainException if platform not registered
     */
    public function getByPlatform(WebhookPlatform $platform): WebhookPlatformConfig
    {
        if (!isset($this->platforms[$platform->value])) {
            throw new \DomainException(\sprintf('Webhook platform %s not registered', $platform->value));
        }

        return $this->platforms[$platform->value];
    }

    /**
     * Find platform config by request path (e.g., /webhooks/shopify).
     *
     * @throws \DomainException if no platform matches the path
     */
    public function getByPath(string $requestPath): WebhookPlatformConfig
    {
        foreach ($this->platforms as $config) {
            foreach ($config->supportedPaths as $path) {
                if (str_ends_with($requestPath, $path)) {
                    return $config;
                }
            }
        }

        throw new \DomainException(\sprintf('No webhook platform registered for path %s', $requestPath));
    }

    /**
     * Find platform config by X-Platform header or infer from path.
     *
     * @throws \DomainException if platform not found
     */
    public function detectPlatform(?string $platformHeader, string $requestPath): WebhookPlatformConfig
    {
        if (null !== $platformHeader) {
            try {
                $platform = WebhookPlatform::from($platformHeader);

                return $this->getByPlatform($platform);
            } catch (\ValueError) {

            }
        }

        return $this->getByPath($requestPath);
    }

    /**
     * Check if a platform is registered.
     */
    public function has(WebhookPlatform $platform): bool
    {
        return isset($this->platforms[$platform->value]);
    }

    /**
     * List all registered platforms.
     *
     * @return array<WebhookPlatform>
     */
    public function listPlatforms(): array
    {
        return array_map(
            static fn (string $value) => WebhookPlatform::from($value),
            array_keys($this->platforms)
        );
    }
}
