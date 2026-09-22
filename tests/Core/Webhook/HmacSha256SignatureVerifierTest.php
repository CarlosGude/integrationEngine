<?php

declare(strict_types=1);
namespace IntegrationEngine\Tests\Core\Webhook;

use IntegrationEngine\Core\Contract\Webhook\SignatureConfig;
use IntegrationEngine\Core\Contract\Webhook\SignatureType;
use IntegrationEngine\Core\Exception\WebhookRejectionReason;
use IntegrationEngine\Core\Exception\WebhookSignatureException;
use IntegrationEngine\Core\Webhook\HmacSha256SignatureVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HmacSha256SignatureVerifierTest extends TestCase
{
    #[DataProvider('acceptedPrefixes')]
    public function testAcceptsValidSignature(?string $prefix): void
    {
        (new HmacSha256SignatureVerifier())->verify('body', ['X-SIGNATURE' => [($prefix ?? '').hash_hmac('sha256', 'body', 'secret')]], new SignatureConfig(SignatureType::HmacSha256, 'x-signature', 'secret', prefix: $prefix));
        self::addToAssertionCount(1);
    }
    /** @return iterable<string, array{?string}> */
    public static function acceptedPrefixes(): iterable
    {
        yield 'no prefix' => [null];
        yield 'explicit prefix' => ['sha256='];
        yield 'empty prefix' => [''];
    }
    /** @param array<string, list<string>> $headers */
    #[DataProvider('rejectedHeaders')]
    public function testRejectsWithoutLeakingSecrets(array $headers, WebhookRejectionReason $reason): void
    {
        $expected = hash_hmac('sha256', 'body', 'SECRET');
        try {
            (new HmacSha256SignatureVerifier())->verify('body', $headers, new SignatureConfig(SignatureType::HmacSha256, 'x-signature', 'SECRET', prefix: 'sha256='));
            self::fail('Signature was accepted');
        } catch (WebhookSignatureException $error) {
            self::assertSame($reason, $error->reason());
            self::assertStringNotContainsString('SECRET', $error->getMessage());
            self::assertStringNotContainsString($expected, $error->getMessage());
        }
    }
    /** @return iterable<string, array{array<string, list<string>>, WebhookRejectionReason}> */
    public static function rejectedHeaders(): iterable
    {
        yield 'missing' => [[], WebhookRejectionReason::HeaderMissing];
        yield 'empty' => [['x-signature' => ['']], WebhookRejectionReason::HeaderMalformed];
        yield 'duplicate' => [['x-signature' => ['a','b']], WebhookRejectionReason::HeaderMalformed];
        yield 'wrong prefix' => [['x-signature' => ['bad=abc']], WebhookRejectionReason::HeaderMalformed];
        yield 'not hex' => [['x-signature' => ['sha256=xyz']], WebhookRejectionReason::HeaderMalformed];
        yield 'invalid' => [['x-signature' => ['sha256='.str_repeat('0',64)]], WebhookRejectionReason::SignatureInvalid];
        yield 'tampered body' => [['x-signature' => ['sha256='.hash_hmac('sha256','other','SECRET')]], WebhookRejectionReason::SignatureInvalid];
    }
}
