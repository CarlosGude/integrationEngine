<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core\Webhook;

use IntegrationEngine\Core\Contract\Webhook\SignatureConfig;
use IntegrationEngine\Core\Contract\Webhook\SignatureType;
use IntegrationEngine\Core\Exception\WebhookRejectionReason;
use IntegrationEngine\Core\Exception\WebhookSignatureException;
use IntegrationEngine\Core\Webhook\TimestampedHmacSignatureVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class TimestampedHmacSignatureVerifierTest extends TestCase
{
    #[DataProvider('provideAcceptsExactlyAtToleranceBoundaryCases')]
    public function testAcceptsExactlyAtToleranceBoundary(int $timestamp): void
    {
        $body = '{ "id": 2, "name": "raw" }';
        $hash = hash_hmac('sha256', $timestamp.'.'.$body, 'SECRET');
        $this->verifier()->verify($body, ['stripe-signature' => ['t='.$timestamp.',v1=wrong,v0=ignored,v1='.$hash]], $this->config());
        self::addToAssertionCount(1);
    }

    /** @return iterable<array{int}> */
    public static function provideAcceptsExactlyAtToleranceBoundaryCases(): iterable
    {
        yield [700];

        yield [1000];

        yield [1300];
    }

    #[DataProvider('provideRejectsMalformedExpiredAndInvalidSignaturesCases')]
    public function testRejectsMalformedExpiredAndInvalidSignatures(string $signature, WebhookRejectionReason $reason): void
    {
        try {
            $this->verifier()->verify('body', ['stripe-signature' => [$signature]], $this->config());
            self::fail('Signature was accepted');
        } catch (WebhookSignatureException $error) {
            self::assertSame($reason, $error->reason());
            self::assertStringNotContainsString('SECRET', $error->getMessage());
            self::assertStringNotContainsString($signature, $error->getMessage());
        }
    }

    /** @return iterable<string, array{string, WebhookRejectionReason}> */
    public static function provideRejectsMalformedExpiredAndInvalidSignaturesCases(): iterable
    {
        foreach (['missing' => 'v1=abc', 'nonnumeric' => 't=oops,v1=abc', 'decimal' => 't=1.0,v1=abc', 'duplicate' => 't=1000,t=1000,v1=abc', 'overflow' => 't=9999999999999999999999,v1=abc'] as $key => $value) {
            yield $key => [$value, WebhookRejectionReason::HeaderMalformed];
        }
        foreach ([699, 1301] as $time) {
            yield 'expired '.$time => ['t='.$time.',v1='.hash_hmac('sha256', $time.'.body', 'SECRET'), WebhookRejectionReason::TimestampOutOfTolerance];
        }

        yield 'invalid' => ['t=1000,v1=wrong,v1=also-wrong', WebhookRejectionReason::SignatureInvalid];

        yield 'v0 ignored' => ['t=1000,v0='.hash_hmac('sha256', '1000.body', 'SECRET'), WebhookRejectionReason::SignatureInvalid];

        yield 'tampered raw body' => ['t=1000,v1='.hash_hmac('sha256', '1000.other', 'SECRET'), WebhookRejectionReason::SignatureInvalid];
    }

    private function verifier(): TimestampedHmacSignatureVerifier
    {
        return new TimestampedHmacSignatureVerifier(new class implements ClockInterface {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('@1000');
            }
        });
    }

    private function config(): SignatureConfig
    {
        return new SignatureConfig(SignatureType::TimestampedHmac, 'Stripe-Signature', 'SECRET', 300);
    }
}
