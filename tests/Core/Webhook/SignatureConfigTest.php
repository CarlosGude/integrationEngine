<?php

declare(strict_types=1);
namespace IntegrationEngine\Tests\Core\Webhook;
use IntegrationEngine\Core\Contract\Webhook\SignatureConfig;
use IntegrationEngine\Core\Contract\Webhook\SignatureType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
final class SignatureConfigTest extends TestCase
{
    public function testParsesValidConfiguration(): void
    {
        $config = SignatureConfig::fromArray(['type'=>'timestamped_hmac','header'=>'Stripe-Signature','secret'=>'secret','tolerance'=>300]);
        self::assertSame(SignatureType::TimestampedHmac,$config->type);
        self::assertSame('Stripe-Signature',$config->header);
        self::assertSame('secret',$config->secret);
        self::assertSame(300,$config->tolerance);
        self::assertNull($config->prefix);
    }
    /** @param array<string, mixed> $changes */
    #[DataProvider('invalidConfigurations')]
    public function testRejectsInvalidConfiguration(array $changes): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SignatureConfig::fromArray(array_replace(['type'=>'hmac_sha256','header'=>'x-signature','secret'=>'secret'],$changes));
    }
    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidConfigurations(): iterable
    {
        yield 'unknown type' => [['type'=>'unknown']];
        yield 'empty header' => [['header'=>'']];
        yield 'empty secret' => [['secret'=>'']];
        yield 'missing type' => [['type'=>null]];
        yield 'noninteger tolerance' => [['tolerance'=>'300']];
        yield 'negative tolerance' => [['type'=>'timestamped_hmac','tolerance'=>-1]];
        yield 'missing tolerance' => [['type'=>'timestamped_hmac']];
        yield 'forbidden tolerance' => [['tolerance'=>300]];
        yield 'forbidden prefix' => [['type'=>'timestamped_hmac','tolerance'=>300,'prefix'=>'sha256=']];
        yield 'invalid prefix' => [['prefix'=>1]];
    }
}
