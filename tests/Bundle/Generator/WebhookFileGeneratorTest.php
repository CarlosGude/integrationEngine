<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Bundle\Generator;

use IntegrationEngine\Bundle\Generator\WebhookContext;
use IntegrationEngine\Bundle\Generator\WebhookFileGenerator;
use PHPUnit\Framework\TestCase;

final class WebhookFileGeneratorTest extends TestCase
{
    private WebhookFileGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new WebhookFileGenerator();
    }

    public function testGenerateWebhookEventFile(): void
    {
        $ctx = new WebhookContext(
            integration: 'stripe',
            event: 'charge.succeeded',
            verifierType: 'timestamped_hmac',
            headerName: 'Stripe-Signature',
            baseNamespace: 'App\Webhooks',
            basePath: '/tmp/webhooks',
        );

        $files = $this->generator->generateFiles($ctx);

        self::assertCount(2, $files);
        self::assertArrayHasKey('/tmp/webhooks/Stripe/ChargeSucceededEvent.php', $files);
        self::assertArrayHasKey('/tmp/webhooks/Stripe/ChargeSucceededRequestParser.php', $files);
    }

    public function testEventFileImplementsWebhookEventInterface(): void
    {
        $ctx = new WebhookContext(
            integration: 'paypal',
            event: 'payment.sale.completed',
            verifierType: 'hmac_sha256',
            headerName: 'X-Webhook-Signature',
            baseNamespace: 'App\Webhooks',
            basePath: '/tmp/webhooks',
        );

        $files = $this->generator->generateFiles($ctx);
        $eventFile = $files['/tmp/webhooks/Paypal/PaymentSaleCompletedEvent.php'];

        self::assertStringContainsString('implements WebhookEventInterface', $eventFile);
        self::assertStringContainsString('public readonly string $id', $eventFile);
        self::assertStringContainsString('public readonly string $type', $eventFile);
    }

    public function testParserExtendsIntegrationWebhookRequestParser(): void
    {
        $ctx = new WebhookContext(
            integration: 'stripe',
            event: 'charge.succeeded',
            verifierType: 'timestamped_hmac',
            headerName: 'Stripe-Signature',
            baseNamespace: 'App\Webhooks',
            basePath: '/tmp/webhooks',
        );

        $files = $this->generator->generateFiles($ctx);
        $parserFile = $files['/tmp/webhooks/Stripe/ChargeSucceededRequestParser.php'];

        self::assertStringContainsString('extends IntegrationWebhookRequestParser', $parserFile);
        self::assertStringContainsString('public function getDefinition(): string', $parserFile);
        self::assertStringContainsString("return 'charge.succeeded'", $parserFile);
        self::assertStringContainsString('protected function getSignatureVerifier(): SignatureVerifierInterface', $parserFile);
        self::assertStringContainsString('protected function getSignatureSecret(): string', $parserFile);
    }

    public function testHmacSha256VerifierImported(): void
    {
        $ctx = new WebhookContext(
            integration: 'webhook',
            event: 'test.event',
            verifierType: 'hmac_sha256',
            headerName: 'X-Signature',
            baseNamespace: 'App\Webhooks',
            basePath: '/tmp',
        );

        $files = $this->generator->generateFiles($ctx);
        $parserFile = $files['/tmp/Webhook/TestEventRequestParser.php'];

        self::assertStringContainsString('use IntegrationEngine\Core\Webhook\HmacSha256SignatureVerifier;', $parserFile);
    }

    public function testTimestampedHmacVerifierImported(): void
    {
        $ctx = new WebhookContext(
            integration: 'webhook',
            event: 'test.event',
            verifierType: 'timestamped_hmac',
            headerName: 'X-Signature',
            baseNamespace: 'App\Webhooks',
            basePath: '/tmp',
        );

        $files = $this->generator->generateFiles($ctx);
        $parserFile = $files['/tmp/Webhook/TestEventRequestParser.php'];

        self::assertStringContainsString('use IntegrationEngine\Core\Webhook\TimestampedHmacSignatureVerifier;', $parserFile);
    }
}
