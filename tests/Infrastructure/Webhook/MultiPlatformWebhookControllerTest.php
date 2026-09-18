<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Webhook;

use IntegrationEngine\Core\Webhook\ShopifyHmacSignatureVerifier;
use IntegrationEngine\Core\Webhook\WebhookPlatform;
use IntegrationEngine\Core\Webhook\WebhookPlatformConfig;
use IntegrationEngine\Infrastructure\Webhook\Controller\MultiPlatformWebhookController;
use IntegrationEngine\Infrastructure\Webhook\WebhookEventRegistry;
use IntegrationEngine\Infrastructure\Webhook\WebhookPlatformRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class MultiPlatformWebhookControllerTest extends TestCase
{
    private const SECRET = 'shpss_test';
    private const BODY = '{"id":123}';

    #[Test]
    public function acceptsARequestSignedWithThePlatformSecret(): void
    {
        $response = $this->controller(self::SECRET)->ingest($this->request($this->sign(self::SECRET)), 'shopify');

        self::assertSame(202, $response->getStatusCode());
    }

    #[Test]
    public function rejectsARequestSignedWithAnEmptyKey(): void
    {
        // Used to be accepted: the controller verified with ''.
        $response = $this->controller(self::SECRET)->ingest($this->request($this->sign('')), 'shopify');

        self::assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function refusesEverythingWhileNoSecretIsConfigured(): void
    {
        $response = $this->controller('')->ingest($this->request($this->sign('')), 'shopify');

        self::assertSame(500, $response->getStatusCode());
    }

    private function controller(string $secret): MultiPlatformWebhookController
    {
        $registry = new WebhookPlatformRegistry();
        $registry->register(new WebhookPlatformConfig(
            platform: WebhookPlatform::SHOPIFY,
            verifier: new ShopifyHmacSignatureVerifier(),
            eventRegistry: new WebhookEventRegistry(),
            supportedPaths: ['/webhooks/shopify'],
            secret: $secret,
        ));

        return new MultiPlatformWebhookController($registry);
    }

    private function sign(string $key): string
    {
        return base64_encode(hash_hmac('sha256', self::BODY, $key, true));
    }

    private function request(string $signature): Request
    {
        return Request::create(
            uri: '/webhooks/shopify',
            method: 'POST',
            server: ['HTTP_X_SHOPIFY_HMAC_SHA256' => $signature],
            content: self::BODY,
        );
    }
}
