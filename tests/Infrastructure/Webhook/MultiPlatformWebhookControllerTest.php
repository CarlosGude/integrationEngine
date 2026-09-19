<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Webhook;

use IntegrationEngine\Core\Contract\Webhook\SignatureVerifierInterface;
use IntegrationEngine\Core\Webhook\ShopifyHmacSignatureVerifier;
use IntegrationEngine\Core\Webhook\WebhookPlatform;
use IntegrationEngine\Core\Webhook\WebhookPlatformConfig;
use IntegrationEngine\Infrastructure\Webhook\Controller\MultiPlatformWebhookController;
use IntegrationEngine\Infrastructure\Webhook\WebhookEventRegistry;
use IntegrationEngine\Infrastructure\Webhook\WebhookPlatformRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class MultiPlatformWebhookControllerTest extends TestCase
{
    private const SECRET = 'shpss_test';
    private const BODY = '{"id":123}';

    #[Test]
    public function acceptsARequestSignedWithThePlatformSecret(): void
    {
        $response = $this->controller(self::SECRET)->ingest($this->request($this->sign(self::SECRET)));

        self::assertResponse(202, ['status' => 'accepted'], $response);
    }

    #[Test]
    public function rejectsARequestSignedWithAnEmptyKey(): void
    {
        // Used to be accepted: the controller verified with ''.
        $response = $this->controller(self::SECRET)->ingest($this->request($this->sign('')));

        self::assertResponse(401, ['error' => 'Invalid webhook signature'], $response);
    }

    #[Test]
    public function refusesEverythingWhileNoSecretIsConfigured(): void
    {
        $response = $this->controller('')->ingest($this->request($this->sign('')));

        self::assertResponse(500, ['error' => 'Webhook secret not configured for this platform'], $response);
    }

    #[Test]
    public function rejectsARequestWithoutTheSignatureHeader(): void
    {
        $response = $this->controller(self::SECRET)->ingest($this->request(null));

        self::assertResponse(400, ['error' => 'Missing signature header: X-Shopify-Hmac-SHA256'], $response);
    }

    #[Test]
    public function rejectsAMissingSignatureBeforeCheckingTheSecret(): void
    {
        $response = $this->controller('')->ingest($this->request(null));

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function rejectsAPathNoPlatformIsRegisteredFor(): void
    {
        $response = $this->controller(self::SECRET)->ingest($this->request($this->sign(self::SECRET), '/webhooks/woocommerce'));

        self::assertResponse(400, ['error' => 'No webhook platform registered for path /webhooks/woocommerce'], $response);
    }

    #[Test]
    public function rejectsAnXPlatformHeaderForAnUnregisteredPlatform(): void
    {
        $request = $this->request($this->sign(self::SECRET));
        $request->headers->set('X-Platform', 'woocommerce');

        $response = $this->controller(self::SECRET)->ingest($request);

        self::assertResponse(400, ['error' => 'Webhook platform woocommerce not registered'], $response);
    }

    #[Test]
    public function answersBadRequestWhenTheVerifierRejectsItsInput(): void
    {
        $verifier = $this->throwingVerifier(new \InvalidArgumentException('Malformed signature'));

        $response = $this->controller(self::SECRET, $verifier)->ingest($this->request($this->sign(self::SECRET)));

        self::assertResponse(400, ['error' => 'Malformed signature'], $response);
    }

    #[Test]
    public function hidesUnexpectedFailuresBehindAGenericError(): void
    {
        $verifier = $this->throwingVerifier(new \RuntimeException('secret detail'));

        $response = $this->controller(self::SECRET, $verifier)->ingest($this->request($this->sign(self::SECRET)));

        self::assertResponse(500, ['error' => 'Webhook processing failed'], $response);
    }

    /**
     * @param array<string, string> $expectedBody
     */
    private static function assertResponse(int $expectedStatus, array $expectedBody, JsonResponse $response): void
    {
        self::assertSame($expectedStatus, $response->getStatusCode());
        self::assertSame($expectedBody, json_decode((string) $response->getContent(), true));
    }

    private function controller(string $secret, ?SignatureVerifierInterface $verifier = null): MultiPlatformWebhookController
    {
        $registry = new WebhookPlatformRegistry();
        $registry->register(new WebhookPlatformConfig(
            platform: WebhookPlatform::SHOPIFY,
            verifier: $verifier ?? new ShopifyHmacSignatureVerifier(),
            eventRegistry: new WebhookEventRegistry(),
            supportedPaths: ['/webhooks/shopify'],
            secret: $secret,
        ));

        return new MultiPlatformWebhookController($registry);
    }

    private function throwingVerifier(\Exception $exception): SignatureVerifierInterface
    {
        return new class($exception) implements SignatureVerifierInterface {
            public function __construct(private \Exception $exception) {}

            public function verify(string $body, string $signature, string $secret): bool
            {
                throw $this->exception;
            }

            public function getHeaderName(): string
            {
                return 'X-Shopify-Hmac-SHA256';
            }
        };
    }

    private function sign(string $key): string
    {
        return base64_encode(hash_hmac('sha256', self::BODY, $key, true));
    }

    private function request(?string $signature, string $path = '/webhooks/shopify'): Request
    {
        return Request::create(
            uri: $path,
            method: 'POST',
            server: null === $signature ? [] : ['HTTP_X_SHOPIFY_HMAC_SHA256' => $signature],
            content: self::BODY,
        );
    }
}
