<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core\Webhook;

use IntegrationEngine\Core\Contract\Webhook\SignatureConfig;
use IntegrationEngine\Core\Contract\Webhook\SignatureType;
use IntegrationEngine\Core\Exception\WebhookRejectionReason;
use IntegrationEngine\Core\Exception\WebhookSignatureException;
use IntegrationEngine\Core\Webhook\HmacSha256SignatureVerifier;
use PHPUnit\Framework\TestCase;

final class V8SignatureContractTest extends TestCase
{
    public function testAcceptsRawBodyWithCaseInsensitiveHeader(): void
    {
        $body = '{ "id": "secret-payload" }';
        $config = new SignatureConfig(SignatureType::HmacSha256, 'X-Signature', 'private-key');
        (new HmacSha256SignatureVerifier())->verify($body, ['X-SIGNATURE' => [hash_hmac('sha256', $body, 'private-key')]], $config);
        self::addToAssertionCount(1);
    }

    public function testMissingHeaderCarriesSafeStructuredReason(): void
    {
        try {
            (new HmacSha256SignatureVerifier())->verify('body', [], new SignatureConfig(SignatureType::HmacSha256, 'X-Signature', 'private-key'));
            self::fail('Missing header was accepted');
        } catch (WebhookSignatureException $error) {
            self::assertSame(WebhookRejectionReason::HeaderMissing, $error->reason());
            self::assertStringNotContainsString('private-key', $error->getMessage());
        }
    }
}
