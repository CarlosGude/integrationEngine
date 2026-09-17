<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core\Webhook;

use IntegrationEngine\Core\Webhook\TimestampedHmacSignatureVerifier;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class TimestampedHmacSignatureVerifierTest extends TestCase
{
    private const SECRET = 'test_secret_key_12345';
    private const TOLERANCE = 300; // 5 minutes

    public function testVerifyValidSignatureWithinTolerance(): void
    {
        $clock = new FakeClock(1000);
        $verifier = new TimestampedHmacSignatureVerifier('Stripe-Signature', self::TOLERANCE, $clock);

        $timestamp = 1000;
        $body = 'test body';
        $hash = hash_hmac('sha256', "{$timestamp}.{$body}", self::SECRET);
        $signature = "t={$timestamp},v1={$hash}";

        self::assertTrue($verifier->verify($body, $signature, self::SECRET));
    }

    public function testRejectSignatureTooOld(): void
    {
        $clock = new FakeClock(2000);
        $verifier = new TimestampedHmacSignatureVerifier('Stripe-Signature', self::TOLERANCE, $clock);

        $timestamp = 1000;
        $body = 'test body';
        $hash = hash_hmac('sha256', "{$timestamp}.{$body}", self::SECRET);
        $signature = "t={$timestamp},v1={$hash}";

        // Current time 2000, timestamp 1000, difference 1000 > tolerance 300
        self::assertFalse($verifier->verify($body, $signature, self::SECRET));
    }

    public function testRejectSignatureInTheFuture(): void
    {
        $clock = new FakeClock(1000);
        $verifier = new TimestampedHmacSignatureVerifier('Stripe-Signature', self::TOLERANCE, $clock);

        $timestamp = 2000;
        $body = 'test body';
        $hash = hash_hmac('sha256', "{$timestamp}.{$body}", self::SECRET);
        $signature = "t={$timestamp},v1={$hash}";

        // Timestamp in future
        self::assertFalse($verifier->verify($body, $signature, self::SECRET));
    }

    public function testVerifyWithMultipleVersions(): void
    {
        $clock = new FakeClock(1000);
        $verifier = new TimestampedHmacSignatureVerifier('Stripe-Signature', self::TOLERANCE, $clock);

        $timestamp = 1000;
        $body = 'test body';

        // Multiple v1 hashes (key rotation)
        $hash1 = hash_hmac('sha256', "{$timestamp}.{$body}", 'old_secret');
        $hash2 = hash_hmac('sha256', "{$timestamp}.{$body}", self::SECRET);
        $signature = "t={$timestamp},v1={$hash1},v1={$hash2}";

        // Should succeed because one of the v1 values matches
        self::assertTrue($verifier->verify($body, $signature, self::SECRET));
    }

    public function testRejectWhenNoValidVersionsMatch(): void
    {
        $clock = new FakeClock(1000);
        $verifier = new TimestampedHmacSignatureVerifier('Stripe-Signature', self::TOLERANCE, $clock);

        $timestamp = 1000;
        $body = 'test body';

        $hash1 = hash_hmac('sha256', "{$timestamp}.{$body}", 'wrong_secret_1');
        $hash2 = hash_hmac('sha256', "{$timestamp}.{$body}", 'wrong_secret_2');
        $signature = "t={$timestamp},v1={$hash1},v1={$hash2}";

        self::assertFalse($verifier->verify($body, $signature, self::SECRET));
    }

    public function testIgnoreV0Versions(): void
    {
        $clock = new FakeClock(1000);
        $verifier = new TimestampedHmacSignatureVerifier('Stripe-Signature', self::TOLERANCE, $clock);

        $timestamp = 1000;
        $body = 'test body';

        // v0 should be ignored
        $v0Hash = hash_hmac('sha1', "{$timestamp}.{$body}", self::SECRET);
        $v1Hash = hash_hmac('sha256', "{$timestamp}.{$body}", self::SECRET);
        $signature = "t={$timestamp},v0={$v0Hash},v1={$v1Hash}";

        self::assertTrue($verifier->verify($body, $signature, self::SECRET));
    }

    public function testRejectMalformedSignatureMissingTimestamp(): void
    {
        $clock = new FakeClock(1000);
        $verifier = new TimestampedHmacSignatureVerifier('Stripe-Signature', self::TOLERANCE, $clock);

        $body = 'test body';
        $hash = hash_hmac('sha256', '1000.'.$body, self::SECRET);
        $signature = "v1={$hash}"; // Missing t=

        self::assertFalse($verifier->verify($body, $signature, self::SECRET));
    }

    public function testRejectMalformedSignatureMissingVersions(): void
    {
        $clock = new FakeClock(1000);
        $verifier = new TimestampedHmacSignatureVerifier('Stripe-Signature', self::TOLERANCE, $clock);

        $timestamp = 1000;
        $body = 'test body';
        $signature = "t={$timestamp}"; // Missing v1=

        self::assertFalse($verifier->verify($body, $signature, self::SECRET));
    }

    public function testRejectInvalidTimestampFormat(): void
    {
        $clock = new FakeClock(1000);
        $verifier = new TimestampedHmacSignatureVerifier('Stripe-Signature', self::TOLERANCE, $clock);

        $body = 'test body';
        $hash = hash_hmac('sha256', 'not_a_timestamp.'.$body, self::SECRET);
        $signature = "t=not_a_timestamp,v1={$hash}";

        self::assertFalse($verifier->verify($body, $signature, self::SECRET));
    }

    public function testGetHeaderName(): void
    {
        $clock = new FakeClock(1000);
        $verifier = new TimestampedHmacSignatureVerifier('Stripe-Signature', self::TOLERANCE, $clock);

        self::assertSame('Stripe-Signature', $verifier->getHeaderName());
    }

    public function testRejectModifiedBody(): void
    {
        $clock = new FakeClock(1000);
        $verifier = new TimestampedHmacSignatureVerifier('Stripe-Signature', self::TOLERANCE, $clock);

        $timestamp = 1000;
        $body = 'test body';
        $hash = hash_hmac('sha256', "{$timestamp}.{$body}", self::SECRET);
        $signature = "t={$timestamp},v1={$hash}";

        // Modify body
        $modifiedBody = 'modified test body';

        self::assertFalse($verifier->verify($modifiedBody, $signature, self::SECRET));
    }
}

/**
 * Fake clock for testing with specific times.
 */
final class FakeClock implements ClockInterface
{
    public function __construct(private int $currentTime) {}

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable("@{$this->currentTime}");
    }
}
