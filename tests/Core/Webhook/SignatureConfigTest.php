<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core\Webhook;

use IntegrationEngine\Core\Contract\Webhook\SignatureConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The validation SignatureConfig does on the pair (type, timestamp tolerance):
 * a timestamped HMAC is unverifiable without a tolerance, and a plain HMAC has
 * nothing to do with one.
 */
final class SignatureConfigTest extends TestCase
{
    #[Test]
    public function timestampedHmacRequiresATolerance(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Signature type "timestamped_hmac" requires "timestamp_tolerance" to be set (in seconds).');

        new SignatureConfig('timestamped_hmac', 'Stripe-Signature');
    }

    #[Test]
    public function plainHmacRejectsATolerance(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Signature type "hmac_sha256" does not support "timestamp_tolerance".');

        new SignatureConfig('hmac_sha256', 'X-Signature', 300);
    }

    #[Test]
    public function fromArrayRejectsANonIntegerTolerance(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Signature config "timestamp_tolerance" must be an integer.');

        SignatureConfig::fromArray([
            'type' => 'timestamped_hmac',
            'header' => 'Stripe-Signature',
            'timestamp_tolerance' => '300',
        ]);
    }

    #[Test]
    public function fromArrayAcceptsAnAbsentTolerance(): void
    {
        $config = SignatureConfig::fromArray([
            'type' => 'hmac_sha256',
            'header' => 'X-Signature',
        ]);

        self::assertSame('hmac_sha256', $config->getType());
        self::assertSame('X-Signature', $config->getHeader());
        self::assertNull($config->getTimestampTolerance());
    }
}
