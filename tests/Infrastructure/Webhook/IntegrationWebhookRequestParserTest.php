<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Webhook;

use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\SignatureConfig;
use IntegrationEngine\Core\Contract\Webhook\SignatureType;
use IntegrationEngine\Core\Contract\Webhook\UnknownEventPolicy;
use IntegrationEngine\Core\Contract\Webhook\WebhookDefinition;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;
use IntegrationEngine\Core\Exception\WebhookRejectionReason;
use IntegrationEngine\Core\Webhook\HmacSha256SignatureVerifier;
use IntegrationEngine\Infrastructure\Webhook\IntegrationWebhookRequestParser;
use IntegrationEngine\Infrastructure\Webhook\MappedRemoteEvent;
use IntegrationEngine\Infrastructure\Webhook\WebhookRejectedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

final class IntegrationWebhookRequestParserTest extends TestCase
{
    public function testMapsAnAuthenticatedEventAndPreservesRawDecodedPayload(): void
    {
        $payload = ['id' => 'evt_123', 'type' => 'payment.completed', 'data' => ['amount' => 100]];
        $event = $this->parser()->parse($this->request(json_encode($payload, JSON_THROW_ON_ERROR)), 'routing-secret-is-ignored');
        self::assertInstanceOf(MappedRemoteEvent::class, $event);
        self::assertSame('payment.completed', $event->getName());
        self::assertSame('evt_123', $event->getId());
        self::assertSame($payload, $event->getPayload());
        self::assertInstanceOf(TestWebhookEvent::class, $event->event());
    }

    public function testIgnoresUnknownAuthenticatedEvent(): void
    {
        self::assertNull($this->parser(UnknownEventPolicy::Ignore)->parse($this->request('{"id":"evt","type":"unknown"}'), ''));
    }

    public function testAcceptsStructuredJsonContentType(): void
    {
        self::assertInstanceOf(MappedRemoteEvent::class, $this->parser()->parse($this->request('{"id":0,"type":"payment.completed"}', contentType: 'application/cloudevents+json; charset=UTF-8'), ''));
    }

    #[DataProvider('provideStructuredRejectionsNeverRevealSecretsCases')]
    public function testStructuredRejectionsNeverRevealSecrets(string $body, ?string $signature, string $method, string $contentType, WebhookRejectionReason $reason): void
    {
        try {
            $this->parser()->parse($this->request($body, $signature, $method, $contentType), '');
            self::fail('Rejected request accepted');
        } catch (RejectWebhookException $error) {
            self::assertSame(406, $error->getStatusCode());
            self::assertSame('Webhook rejected: '.$reason->value, $error->getMessage());
            $previous = $error->getPrevious();
            self::assertInstanceOf(WebhookRejectedException::class, $previous);
            self::assertSame($reason, $previous->reason());
            self::assertStringNotContainsString('SECRET', $error->getMessage().$previous->getMessage());
            self::assertStringNotContainsString(hash_hmac('sha256', $body, 'SECRET'), $error->getMessage().$previous->getMessage());
        }
    }

    /** @return iterable<string, array{string, ?string, string, string, WebhookRejectionReason}> */
    public static function provideStructuredRejectionsNeverRevealSecretsCases(): iterable
    {
        yield 'signature precedes JSON' => ['broken JSON', str_repeat('0', 64), 'POST', 'application/json', WebhookRejectionReason::SignatureInvalid];

        yield 'valid signature invalid JSON' => ['broken JSON', null, 'POST', 'application/json', WebhookRejectionReason::PayloadInvalid];

        yield 'array root' => ['[]', null, 'POST', 'application/json', WebhookRejectionReason::PayloadInvalid];

        yield 'scalar root' => ['42', null, 'POST', 'application/json', WebhookRejectionReason::PayloadInvalid];

        yield 'missing fields' => ['{}', null, 'POST', 'application/json', WebhookRejectionReason::PayloadInvalid];

        yield 'missing id' => ['{"type":"payment.completed"}', null, 'POST', 'application/json', WebhookRejectionReason::PayloadInvalid];

        yield 'boolean id' => ['{"id":false,"type":"payment.completed"}', null, 'POST', 'application/json', WebhookRejectionReason::PayloadInvalid];

        yield 'unknown' => ['{"id":"evt","type":"unknown"}', null, 'POST', 'application/json', WebhookRejectionReason::UnknownEvent];

        yield 'wrong method' => ['{}', null, 'GET', 'application/json', WebhookRejectionReason::PayloadInvalid];

        yield 'wrong content type' => ['{}', null, 'POST', 'text/plain', WebhookRejectionReason::PayloadInvalid];
    }

    private function parser(UnknownEventPolicy $policy = UnknownEventPolicy::Reject): IntegrationWebhookRequestParser
    {
        return new IntegrationWebhookRequestParser(new WebhookDefinition('type', 'id', new SignatureConfig(SignatureType::HmacSha256, 'X-Signature', 'SECRET'), $policy, ['payment.completed' => TestWebhookMapper::class]), new HmacSha256SignatureVerifier(), 'payments');
    }

    private function request(string $body, ?string $signature = null, string $method = 'POST', string $contentType = 'application/json'): Request
    {
        return Request::create('/webhook', $method, server: ['CONTENT_TYPE' => $contentType, 'HTTP_X_SIGNATURE' => $signature ?? hash_hmac('sha256', $body, 'SECRET')], content: $body);
    }
}
final readonly class TestWebhookEvent implements WebhookEventInterface
{
    public function __construct(public string $id) {}
}
final class TestWebhookMapper extends AbstractWebhookMapper
{
    public static function eventType(): string
    {
        return 'payment.completed';
    }

    protected static function transform(array $payload, array $headers): WebhookEventInterface
    {
        $id = $payload['id'];
        if (!\is_string($id) && !\is_int($id)) {
            throw new \UnexpectedValueException('Invalid event id');
        }

        return new TestWebhookEvent((string) $id);
    }
}
