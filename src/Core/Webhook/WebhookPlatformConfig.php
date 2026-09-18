<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Webhook;

use IntegrationEngine\Core\Contract\Webhook\SignatureVerifierInterface;
use IntegrationEngine\Infrastructure\Webhook\WebhookEventRegistry;

/**
 * Configuration for a webhook platform.
 *
 * Bundles platform-specific verifier, event registry, and metadata.
 *
 * @author Carlos Gude
 */
final readonly class WebhookPlatformConfig
{
    public function __construct(
        public WebhookPlatform $platform,
        public SignatureVerifierInterface $verifier,
        public WebhookEventRegistry $eventRegistry,
        /** @var string[] */
        public array $supportedPaths = [],
    ) {}
}
