<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Contract\Webhook\SignatureConfig;
use IntegrationEngine\Core\Contract\Webhook\WebhookDefinition;
use IntegrationEngine\Infrastructure\Adapter\YamlConfigAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class YamlConfigAdapterWebhooksTest extends TestCase
{
    public function testParseWebhookDefinitionWithHmacSignature(): void
    {
        $configPath = $this->createTempYaml([
            'webhooks' => [
                'charge.succeeded' => [
                    'mapper' => 'App\Payments\Infrastructure\Stripe\ChargeSucceededMapper',
                    'signature' => [
                        'type' => 'hmac_sha256',
                        'header' => 'X-Stripe-Signature',
                    ],
                ],
            ],
        ]);

        $adapter = new YamlConfigAdapter($configPath);
        $definition = $adapter->getWebhookDefinition('charge.succeeded');

        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(WebhookDefinition::class, $definition);
        self::assertSame('charge.succeeded', $definition->getEventType());

        /** @phpstan-ignore staticMethod.impossibleType */
        self::assertSame('App\Payments\Infrastructure\Stripe\ChargeSucceededMapper', $definition->getMapperClass());

        $signature = $definition->getSignature();

        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(SignatureConfig::class, $signature);
        self::assertSame('hmac_sha256', $signature->getType());
        self::assertSame('X-Stripe-Signature', $signature->getHeader());
    }

    public function testParseWebhookDefinitionWithTimestampedHmacSignature(): void
    {
        $configPath = $this->createTempYaml([
            'webhooks' => [
                'payment_intent.succeeded' => [
                    'mapper' => 'App\Payments\Infrastructure\Stripe\PaymentIntentSucceededMapper',
                    'signature' => [
                        'type' => 'timestamped_hmac',
                        'header' => 'Stripe-Signature',
                        'timestamp_tolerance' => 300,
                    ],
                ],
            ],
        ]);

        $adapter = new YamlConfigAdapter($configPath);
        $definition = $adapter->getWebhookDefinition('payment_intent.succeeded');

        $signature = $definition->getSignature();
        self::assertSame('timestamped_hmac', $signature->getType());
        self::assertSame('Stripe-Signature', $signature->getHeader());
        self::assertSame(300, $signature->getTimestampTolerance());
    }

    public function testThrowOnUnknownSignatureType(): void
    {
        $configPath = $this->createTempYaml([
            'webhooks' => [
                'unknown.event' => [
                    'mapper' => 'App\SomeMapper',
                    'signature' => [
                        'type' => 'unknown_signature_type',
                        'header' => 'X-Signature',
                    ],
                ],
            ],
        ]);

        $adapter = new YamlConfigAdapter($configPath);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown_signature_type');

        $adapter->getWebhookDefinition('unknown.event');
    }

    public function testThrowOnMissingMapperClass(): void
    {
        $configPath = $this->createTempYaml([
            'webhooks' => [
                'invalid.event' => [
                    // Missing 'mapper' key
                    'signature' => [
                        'type' => 'hmac_sha256',
                        'header' => 'X-Signature',
                    ],
                ],
            ],
        ]);

        $adapter = new YamlConfigAdapter($configPath);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('mapper');
        $this->expectExceptionMessage('invalid.event');

        $adapter->getWebhookDefinition('invalid.event');
    }

    public function testWebhookConfigWithMultipleEvents(): void
    {
        $configPath = $this->createTempYaml([
            'webhooks' => [
                'event1' => [
                    'mapper' => 'App\Mapper1',
                    'signature' => ['type' => 'hmac_sha256', 'header' => 'X-Sig'],
                ],
                'event2' => [
                    'mapper' => 'App\Mapper2',
                    'signature' => ['type' => 'hmac_sha256', 'header' => 'X-Sig'],
                ],
            ],
        ]);

        $adapter = new YamlConfigAdapter($configPath);

        $def1 = $adapter->getWebhookDefinition('event1');

        /** @phpstan-ignore staticMethod.impossibleType */
        self::assertSame('App\Mapper1', $def1->getMapperClass());

        $def2 = $adapter->getWebhookDefinition('event2');

        /** @phpstan-ignore staticMethod.impossibleType */
        self::assertSame('App\Mapper2', $def2->getMapperClass());
    }

    public function testThrowOnMissingSignatureConfig(): void
    {
        $configPath = $this->createTempYaml([
            'webhooks' => [
                'no.signature' => [
                    'mapper' => 'App\Mapper',
                    // Missing 'signature' key
                ],
            ],
        ]);

        $adapter = new YamlConfigAdapter($configPath);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('signature');
        $this->expectExceptionMessage('no.signature');

        $adapter->getWebhookDefinition('no.signature');
    }

    public function testGetWebhookDefinitionThrowsOnUnknownEvent(): void
    {
        $configPath = $this->createTempYaml([
            'webhooks' => [
                'known.event' => [
                    'mapper' => 'App\Mapper',
                    'signature' => ['type' => 'hmac_sha256', 'header' => 'X-Sig'],
                ],
            ],
        ]);

        $adapter = new YamlConfigAdapter($configPath);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown.event');

        $adapter->getWebhookDefinition('unknown.event');
    }

    /**
     * Helper to create a temp YAML file and return its path.
     *
     * @param array<string, mixed> $webhooks
     */
    private function createTempYaml(array $webhooks): string
    {
        $yaml = Yaml::dump($webhooks);
        $path = sys_get_temp_dir().'/'.uniqid('webhook_test_', true).'.yaml';
        file_put_contents($path, $yaml);
        $this->addToAssertionCount(0); // Don't count file write as assertion

        return $path;
    }
}
