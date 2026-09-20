<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Bundle\Generator;

use IntegrationEngine\Bundle\Generator\WebhookContext;
use IntegrationEngine\Bundle\Generator\WebhookFileGenerator;
use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;

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

        self::assertCount(4, $files);
        self::assertArrayHasKey('/tmp/webhooks/Stripe/ChargeSucceededEvent.php', $files);
        self::assertArrayHasKey('/tmp/webhooks/Stripe/ChargeSucceededEventMapper.php', $files);
        self::assertArrayHasKey('/tmp/webhooks/Stripe/ChargeSucceededRequestParser.php', $files);
        self::assertArrayHasKey('/tmp/webhooks/Stripe/ChargeSucceededConsumer.php', $files);
    }

    public function testEventNamesWithSlashesBecomeUsableClassNames(): void
    {
        // Some providers separate their event names with a slash, which is
        // neither a valid class name nor a valid file name.
        $ctx = new WebhookContext(
            integration: 'storefront',
            event: 'products/update',
            verifierType: 'hmac_sha256',
            headerName: 'X-Storefront-Signature',
            baseNamespace: 'App\Webhooks',
            basePath: '/tmp/webhooks',
        );

        self::assertSame('ProductsUpdateEvent', $ctx->eventClassName());
        self::assertSame('ProductsUpdateRequestParser', $ctx->parserClassName());
        self::assertSame('ProductsUpdateConsumer', $ctx->consumerClassName());
        self::assertSame('storefront_products_update', $ctx->routingKey());
        self::assertSame(
            [
                '/tmp/webhooks/Storefront/ProductsUpdateEvent.php',
                '/tmp/webhooks/Storefront/ProductsUpdateEventMapper.php',
                '/tmp/webhooks/Storefront/ProductsUpdateRequestParser.php',
                '/tmp/webhooks/Storefront/ProductsUpdateConsumer.php',
            ],
            array_keys($this->generator->generateFiles($ctx)),
        );

        // The event type itself keeps its slash where it matters.
        $parser = $this->generator->generateFiles($ctx)['/tmp/webhooks/Storefront/ProductsUpdateRequestParser.php'];
        self::assertStringContainsString("return 'products/update';", $parser);
    }

    public function testConsumerIsKeyedByTheSameRoutingNameAsTheUrl(): void
    {
        $ctx = new WebhookContext(
            integration: 'stripe',
            event: 'charge.succeeded',
            verifierType: 'timestamped_hmac',
            headerName: 'Stripe-Signature',
            baseNamespace: 'App\Webhooks',
            basePath: '/tmp/webhooks',
        );

        $consumer = $this->generator->generateFiles($ctx)['/tmp/webhooks/Stripe/ChargeSucceededConsumer.php'];

        self::assertSame('stripe_charge_succeeded', $ctx->routingKey());
        self::assertStringContainsString("#[AsRemoteEventConsumer('stripe_charge_succeeded')]", $consumer);
        // The same key names the route the parser is reachable at.
        self::assertStringContainsString('stripe_charge_succeeded:', $consumer);
        self::assertStringContainsString('service: App\Webhooks\Stripe\ChargeSucceededRequestParser', $consumer);
    }

    public function testConsumerDispatchesThroughTheWebhookEventDispatcher(): void
    {
        $ctx = new WebhookContext(
            integration: 'stripe',
            event: 'charge.succeeded',
            verifierType: 'timestamped_hmac',
            headerName: 'Stripe-Signature',
            baseNamespace: 'App\Webhooks',
            basePath: '/tmp/webhooks',
        );

        $consumer = $this->generator->generateFiles($ctx)['/tmp/webhooks/Stripe/ChargeSucceededConsumer.php'];

        self::assertStringContainsString('implements ConsumerInterface', $consumer);
        // consume() and the event-type check live in the bundle's trait; the
        // generated class only names its mapper.
        self::assertStringContainsString('use IntegrationEngine\Infrastructure\Webhook\ConsumesWebhookEvents;', $consumer);
        self::assertStringContainsString('use ConsumesWebhookEvents;', $consumer);
        self::assertStringContainsString('return new ChargeSucceededEventMapper();', $consumer);
        self::assertStringNotContainsString('function consume(', $consumer);
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

    public function testParserNeedsNoWiringOfItsOwn(): void
    {
        $ctx = new WebhookContext(
            integration: 'stripe',
            event: 'charge.succeeded',
            verifierType: 'hmac_sha256',
            headerName: 'X-Webhook-Signature',
            baseNamespace: 'App\Webhooks',
            basePath: '/tmp/webhooks',
        );

        $parser = $this->generator->generateFiles($ctx)['/tmp/webhooks/Stripe/ChargeSucceededRequestParser.php'];

        // No constructor at all: autowired as it stands, no services.yaml entry.
        self::assertStringNotContainsString('__construct', $parser);
        self::assertStringContainsString("new HmacSha256SignatureVerifier('X-Webhook-Signature', 'sha256=')", $parser);
        // The secret arrives from framework.webhook.routing; this is the fallback.
        self::assertStringContainsString("protected function getSignatureSecret(): string\n    {\n        return '';", $parser);
    }

    public function testTimestampedParserOnlyInjectsAClock(): void
    {
        $ctx = new WebhookContext(
            integration: 'stripe',
            event: 'charge.succeeded',
            verifierType: 'timestamped_hmac',
            headerName: 'Stripe-Signature',
            baseNamespace: 'App\Webhooks',
            basePath: '/tmp/webhooks',
        );

        $parser = $this->generator->generateFiles($ctx)['/tmp/webhooks/Stripe/ChargeSucceededRequestParser.php'];

        // A PSR-20 clock is autowired like any other service, so this one needs
        // no configuration either.
        self::assertStringContainsString('private readonly ClockInterface $clock,', $parser);
        self::assertStringContainsString('use Psr\Clock\ClockInterface;', $parser);
        self::assertStringContainsString("new TimestampedHmacSignatureVerifier('Stripe-Signature', 300, \$this->clock)", $parser);
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
        // Symfony's AbstractRequestParser isn't readonly, and a readonly class
        // can't extend a non-readonly one (fatal error on load).
        self::assertStringContainsString('final class ChargeSucceededRequestParser extends', $parserFile);
        self::assertStringNotContainsString('readonly class ChargeSucceededRequestParser', $parserFile);
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

    public function testGeneratedFilesAreAutoloadableAndLoad(): void
    {
        $dir = sys_get_temp_dir().'/ie-webhook-gen-'.bin2hex(random_bytes(4));
        $baseNamespace = 'IeGeneratedWebhooks'.bin2hex(random_bytes(4));
        $ctx = new WebhookContext(
            integration: 'stripe',
            event: 'charge.succeeded',
            verifierType: 'timestamped_hmac',
            headerName: 'Stripe-Signature',
            baseNamespace: $baseNamespace,
            basePath: $dir,
        );

        foreach ($this->generator->generateFiles($ctx) as $path => $content) {
            if (!is_dir(\dirname($path))) {
                mkdir(\dirname($path), 0o777, true);
            }
            file_put_contents($path, $content);
        }

        // Plain PSR-4: {baseNamespace}\X\Y -> {basePath}/X/Y.php
        $autoload = static function (string $class) use ($baseNamespace, $dir): void {
            $prefix = $baseNamespace.'\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $file = $dir.'/'.str_replace('\\', '/', substr($class, \strlen($prefix))).'.php';
            if (is_file($file)) {
                require_once $file;
            }
        };
        spl_autoload_register($autoload);

        try {
            // The mapper must load from its own file, without the parser.
            $mapperClass = $ctx->namespace().'\\'.$ctx->mapperClassName();
            self::assertTrue(class_exists($mapperClass));
            $mapper = new $mapperClass();
            self::assertInstanceOf(AbstractWebhookMapper::class, $mapper);
            $eventClass = $ctx->eventClassFqn();
            self::assertTrue(class_exists($eventClass));
            self::assertInstanceOf($eventClass, $mapper->map(['id' => 'evt_1'], []));

            // Loading the parser is what used to fatal (readonly extending non-readonly).
            self::assertTrue(class_exists($ctx->parserClassFqn()));

            $consumerClass = $ctx->namespace().'\\'.$ctx->consumerClassName();
            self::assertTrue(class_exists($consumerClass));
            self::assertContains(ConsumerInterface::class, class_implements($consumerClass) ?: []);
        } finally {
            spl_autoload_unregister($autoload);
            foreach (glob($dir.'/*/*.php') ?: [] as $file) {
                unlink($file);
            }
            foreach (glob($dir.'/*', GLOB_ONLYDIR) ?: [] as $subDir) {
                rmdir($subDir);
            }
            rmdir($dir);
        }
    }
}
