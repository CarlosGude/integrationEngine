<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core\Webhook;

use IntegrationEngine\Core\Contract\Webhook\SignatureConfig;
use IntegrationEngine\Core\Contract\Webhook\SignatureType;
use IntegrationEngine\Core\Exception\WebhookSignatureException;
use IntegrationEngine\Core\Webhook\Base64HmacSignatureVerifier;
use PHPUnit\Framework\TestCase;

final class Base64HmacSignatureVerifierTest extends TestCase
{
    public function testAcceptsValidSignature(): void
    {
        (new Base64HmacSignatureVerifier())->verify('body', ['x-signature' => [base64_encode(hash_hmac('sha256', 'body', 'secret', true))]], new SignatureConfig(SignatureType::Base64Hmac, 'x-signature', 'secret'));
        self::addToAssertionCount(1);
    }

    public function testRejectsTamperedBody(): void
    {
        $this->expectException(WebhookSignatureException::class);
        (new Base64HmacSignatureVerifier())->verify('tampered', ['x-signature' => [base64_encode(hash_hmac('sha256', 'body', 'secret', true))]], new SignatureConfig(SignatureType::Base64Hmac, 'x-signature', 'secret'));
    }
}
