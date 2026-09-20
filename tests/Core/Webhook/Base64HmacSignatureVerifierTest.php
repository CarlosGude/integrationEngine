<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core\Webhook;

use IntegrationEngine\Core\Webhook\Base64HmacSignatureVerifier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The raw HMAC-SHA256 digest, base64-encoded and sent whole — the shape
 * several e-commerce platforms use.
 */
final class Base64HmacSignatureVerifierTest extends TestCase
{
    private const SECRET = 'shared_secret';
    private const BODY = '{"id":123,"title":"Product"}';

    #[Test]
    public function acceptsTheBase64EncodedDigestOfTheBody(): void
    {
        $verifier = new Base64HmacSignatureVerifier('X-Signature');

        self::assertTrue($verifier->verify(self::BODY, $this->sign(self::BODY), self::SECRET));
    }

    #[Test]
    public function rejectsTheHexDigestOfTheSameBody(): void
    {
        // The other common shape. Accepting it would mean the encoding is not
        // being checked at all.
        $verifier = new Base64HmacSignatureVerifier('X-Signature');
        $hex = hash_hmac('sha256', self::BODY, self::SECRET);

        self::assertFalse($verifier->verify(self::BODY, $hex, self::SECRET));
    }

    #[Test]
    public function rejectsASignatureMadeWithAnotherSecret(): void
    {
        $verifier = new Base64HmacSignatureVerifier('X-Signature');
        $signature = base64_encode(hash_hmac('sha256', self::BODY, 'another_secret', true));

        self::assertFalse($verifier->verify(self::BODY, $signature, self::SECRET));
    }

    #[Test]
    public function rejectsASignatureOfAnotherBody(): void
    {
        $verifier = new Base64HmacSignatureVerifier('X-Signature');

        self::assertFalse($verifier->verify('{"id":124}', $this->sign(self::BODY), self::SECRET));
    }

    #[Test]
    public function readsTheSignatureFromTheHeaderItWasGiven(): void
    {
        self::assertSame('X-WC-Webhook-Signature', (new Base64HmacSignatureVerifier('X-WC-Webhook-Signature'))->getHeaderName());
        self::assertSame('X-Shopify-Hmac-SHA256', (new Base64HmacSignatureVerifier('X-Shopify-Hmac-SHA256'))->getHeaderName());
    }

    private function sign(string $body): string
    {
        return base64_encode(hash_hmac('sha256', $body, self::SECRET, true));
    }
}
