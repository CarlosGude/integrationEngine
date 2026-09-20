<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core\Webhook;

use IntegrationEngine\Core\Webhook\HmacSha256SignatureVerifier;
use PHPUnit\Framework\TestCase;

final class HmacSha256SignatureVerifierTest extends TestCase
{
    private const SECRET = 'test_secret_key_12345';

    public function testVerifyValidSignature(): void
    {
        $verifier = new HmacSha256SignatureVerifier('X-Signature', '');
        $body = 'request body content';
        $signature = 'sha256='.hash_hmac('sha256', $body, self::SECRET);

        self::assertTrue($verifier->verify($body, $signature, self::SECRET));
    }

    public function testRejectInvalidSignature(): void
    {
        $verifier = new HmacSha256SignatureVerifier('X-Signature', '');
        $body = 'request body content';
        $invalidSignature = 'sha256=deadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef';

        self::assertFalse($verifier->verify($body, $invalidSignature, self::SECRET));
    }

    public function testRejectWhenBodyIsModified(): void
    {
        $verifier = new HmacSha256SignatureVerifier('X-Signature', '');
        $body = 'request body content';
        $signature = 'sha256='.hash_hmac('sha256', $body, self::SECRET);

        // Modify body
        $modifiedBody = 'modified request body content';

        self::assertFalse($verifier->verify($modifiedBody, $signature, self::SECRET));
    }

    public function testRejectWhenSignatureHasWrongPrefix(): void
    {
        $verifier = new HmacSha256SignatureVerifier('X-Signature', '');
        $body = 'request body content';
        $correctHash = hash_hmac('sha256', $body, self::SECRET);
        $wrongPrefixSignature = 'sha1='.$correctHash;

        self::assertFalse($verifier->verify($body, $wrongPrefixSignature, self::SECRET));
    }

    public function testVerifyWithCustomPrefix(): void
    {
        $verifier = new HmacSha256SignatureVerifier('X-Signature', 'v1=');
        $body = 'request body content';
        $hash = hash_hmac('sha256', $body, self::SECRET);
        $signature = 'v1='.$hash;

        self::assertTrue($verifier->verify($body, $signature, self::SECRET));
    }

    public function testRejectSignatureWithoutPrefix(): void
    {
        $verifier = new HmacSha256SignatureVerifier('X-Signature', '');
        $body = 'request body content';
        $hash = hash_hmac('sha256', $body, self::SECRET);
        // No prefix (sha256= expected)

        self::assertFalse($verifier->verify($body, $hash, self::SECRET));
    }

    public function testRejectSignatureWithoutExpectedPrefix(): void
    {
        $verifier = new HmacSha256SignatureVerifier('X-Signature', 'v1=');
        $body = 'request body content';
        $hash = hash_hmac('sha256', $body, self::SECRET);
        $signature = 'sha256='.$hash; // Wrong prefix

        self::assertFalse($verifier->verify($body, $signature, self::SECRET));
    }

    public function testRejectWhenPrefixIsWrongButAsLongAsTheExpectedOne(): void
    {
        // A prefix of the expected length is the case the prefix check has to
        // catch on its own: drop it and the hash still lines up, so the
        // signature would verify with any 7-character prefix.
        $verifier = new HmacSha256SignatureVerifier('X-Signature', '');
        $body = 'request body content';
        // 'sha999=' is exactly as long as the expected 'sha256='.
        $signature = 'sha999='.hash_hmac('sha256', $body, self::SECRET);

        self::assertFalse($verifier->verify($body, $signature, self::SECRET));
    }

    public function testGetHeaderName(): void
    {
        $verifier = new HmacSha256SignatureVerifier('X-Custom-Sig', 'sha256=');

        self::assertSame('X-Custom-Sig', $verifier->getHeaderName());
    }

    public function testVerifyUsesHashEqualsForComparison(): void
    {
        // This test ensures that timing-safe comparison is used.
        // We cannot directly test hash_equals, but we can verify that:
        // - A valid signature passes
        // - An invalid one fails (not just a string mismatch)

        $verifier = new HmacSha256SignatureVerifier('X-Signature', '');
        $body = 'test body';
        $secret = 'secret';

        $validSignature = 'sha256='.hash_hmac('sha256', $body, $secret);
        $invalidSignature = 'sha256=a'.substr(hash_hmac('sha256', $body, $secret), 1);

        self::assertTrue($verifier->verify($body, $validSignature, $secret));
        self::assertFalse($verifier->verify($body, $invalidSignature, $secret));
    }

    public function testVerifyEmptyBody(): void
    {
        $verifier = new HmacSha256SignatureVerifier('X-Signature', '');
        $body = '';
        $signature = 'sha256='.hash_hmac('sha256', $body, self::SECRET);

        self::assertTrue($verifier->verify($body, $signature, self::SECRET));
    }

    public function testVerifyLargeBody(): void
    {
        $verifier = new HmacSha256SignatureVerifier('X-Signature', '');
        $body = str_repeat('x', 10000);
        $signature = 'sha256='.hash_hmac('sha256', $body, self::SECRET);

        self::assertTrue($verifier->verify($body, $signature, self::SECRET));
    }
}
