<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Webhook;

use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Infrastructure\Webhook\IntegrationWebhookRequestParser;
use IntegrationEngine\Infrastructure\Webhook\Mapper\WooCommerceProductUpdatedMapper;
use IntegrationEngine\Infrastructure\Webhook\Parser\VerifiesWooCommerceSignature;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/**
 * A parser that uses the WooCommerce trait declares its event and its mapper,
 * nothing else — the verifier and the secret handling come for free.
 */
final class VerifiesWooCommerceSignatureTest extends TestCase
{
    private const SECRET = 'wc_secret';

    #[Test]
    public function parsesARequestSignedTheWooCommerceWay(): void
    {
        $body = json_encode(['id' => 99, 'name' => 'Product'], JSON_THROW_ON_ERROR);

        $event = $this->parser()->parse($this->request($body, $this->sign($body)), self::SECRET);

        self::assertInstanceOf(RemoteEvent::class, $event);
        self::assertSame('product.updated', $event->getName());
        self::assertSame('99', $event->getId());
    }

    #[Test]
    public function rejectsASignatureThatIsNotBase64EncodedLikeWooCommerceSendsIt(): void
    {
        $body = json_encode(['id' => 99, 'name' => 'Product'], JSON_THROW_ON_ERROR);
        // The hex digest WooCommerce never sends.
        $hexSignature = hash_hmac('sha256', $body, self::SECRET);

        $this->expectException(RejectWebhookException::class);

        $this->parser()->parse($this->request($body, $hexSignature), self::SECRET);
    }

    private function sign(string $body): string
    {
        return base64_encode(hash_hmac('sha256', $body, self::SECRET, true));
    }

    private function request(string $body, string $signature): Request
    {
        return Request::create(
            uri: '/webhook/woocommerce_product_updated',
            method: 'POST',
            server: ['HTTP_X_WC_WEBHOOK_SIGNATURE' => $signature],
            content: $body,
        );
    }

    private function parser(): IntegrationWebhookRequestParser
    {
        return new class extends IntegrationWebhookRequestParser {
            use VerifiesWooCommerceSignature;

            public function getDefinition(): string
            {
                return 'product.updated';
            }

            public function getMapper(): AbstractWebhookMapper
            {
                return new WooCommerceProductUpdatedMapper();
            }

            protected function getSignatureSecret(): string
            {
                return '';
            }
        };
    }
}
