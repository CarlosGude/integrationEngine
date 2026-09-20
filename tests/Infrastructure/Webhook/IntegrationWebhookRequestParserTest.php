<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Webhook;

use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;
use IntegrationEngine\Core\Webhook\HmacSha256SignatureVerifier;
use IntegrationEngine\Infrastructure\Webhook\IntegrationWebhookRequestParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

final class IntegrationWebhookRequestParserTest extends TestCase
{
    private const SECRET = 'test_secret_key';
    private const SIGNATURE_HEADER = 'X-Webhook-Signature';

    public function testParseValidWebhookRequest(): void
    {
        $payload = ['id' => 'evt_123', 'type' => 'payment.completed'];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $hash = hash_hmac('sha256', $body, self::SECRET);
        $signature = "sha256={$hash}";

        $request = Request::create(
            uri: '/webhook',
            method: 'POST',
            server: ['HTTP_X_WEBHOOK_SIGNATURE' => $signature],
            content: $body,
        );

        $parser = new TestWebhookRequestParser(
            new HmacSha256SignatureVerifier(self::SIGNATURE_HEADER, 'sha256='),
            self::SECRET,
        );

        $remoteEvent = $parser->parse($request, self::SECRET);

        self::assertInstanceOf(RemoteEvent::class, $remoteEvent);

        /** @var RemoteEvent $remoteEvent */
        self::assertSame('payment.completed', $remoteEvent->getName());
        self::assertSame('evt_123', $remoteEvent->getId());
        self::assertSame($payload, $remoteEvent->getPayload());
    }

    public function testFallsBackToTheParserSecretWhenTheRoutingSecretIsEmpty(): void
    {
        $body = json_encode(['id' => 'evt_123', 'type' => 'payment.completed'], JSON_THROW_ON_ERROR);
        $request = Request::create(
            uri: '/webhook',
            method: 'POST',
            server: ['HTTP_X_WEBHOOK_SIGNATURE' => 'sha256='.hash_hmac('sha256', $body, self::SECRET)],
            content: $body,
        );

        $parser = new TestWebhookRequestParser(
            new HmacSha256SignatureVerifier(self::SIGNATURE_HEADER, 'sha256='),
            self::SECRET,
        );

        self::assertInstanceOf(RemoteEvent::class, $parser->parse($request, ''));
    }

    public function testRejectsWhenNoSecretIsConfigured(): void
    {
        // Signed with an empty key: this used to be accepted.
        $body = json_encode(['id' => 'evt_123', 'type' => 'payment.completed'], JSON_THROW_ON_ERROR);
        $request = Request::create(
            uri: '/webhook',
            method: 'POST',
            server: ['HTTP_X_WEBHOOK_SIGNATURE' => 'sha256='.hash_hmac('sha256', $body, '')],
            content: $body,
        );

        $parser = new TestWebhookRequestParser(
            new HmacSha256SignatureVerifier(self::SIGNATURE_HEADER, 'sha256='),
            '',
        );

        try {
            $parser->parse($request, '');
            self::fail('Expected RejectWebhookException');
        } catch (RejectWebhookException $e) {
            self::assertSame(406, $e->getStatusCode());
        }
    }

    public function testRejectWebhookWithInvalidSignature(): void
    {
        $payload = ['id' => 'evt_123', 'type' => 'payment.completed'];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $request = Request::create(
            uri: '/webhook',
            method: 'POST',
            server: ['HTTP_X_WEBHOOK_SIGNATURE' => 'sha256=invalid_hash'],
            content: $body,
        );

        $parser = new TestWebhookRequestParser(
            new HmacSha256SignatureVerifier(self::SIGNATURE_HEADER, 'sha256='),
            self::SECRET,
        );

        try {
            $parser->parse($request, self::SECRET);
            self::fail('Expected RejectWebhookException');
        } catch (RejectWebhookException $e) {
            self::assertSame(406, $e->getStatusCode());
        }
    }

    public function testRejectWebhookWhenSignatureHeaderMissing(): void
    {
        $payload = ['id' => 'evt_123', 'type' => 'payment.completed'];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $request = Request::create(
            uri: '/webhook',
            method: 'POST',
            content: $body,
        );

        $parser = new TestWebhookRequestParser(
            new HmacSha256SignatureVerifier(self::SIGNATURE_HEADER, 'sha256='),
            self::SECRET,
        );

        try {
            $parser->parse($request, self::SECRET);
            self::fail('Expected RejectWebhookException');
        } catch (RejectWebhookException $e) {
            self::assertSame(406, $e->getStatusCode());
        }
    }

    public function testRejectWebhookWithMalformedJson(): void
    {
        $hash = hash_hmac('sha256', 'invalid json', self::SECRET);
        $signature = "sha256={$hash}";

        $request = Request::create(
            uri: '/webhook',
            method: 'POST',
            server: ['HTTP_X_WEBHOOK_SIGNATURE' => $signature],
            content: 'invalid json',
        );

        $parser = new TestWebhookRequestParser(
            new HmacSha256SignatureVerifier(self::SIGNATURE_HEADER, 'sha256='),
            self::SECRET,
        );

        try {
            $parser->parse($request, self::SECRET);
            self::fail('Expected RejectWebhookException');
        } catch (RejectWebhookException $e) {
            self::assertSame(406, $e->getStatusCode());
        }
    }

    public function testRejectWebhookSentWithAnotherMethodThanPost(): void
    {
        // Everything else about this request is valid: only the method is wrong.
        try {
            $this->parse($this->signedRequest(['id' => 'evt_123'], method: 'GET'));
            self::fail('Expected RejectWebhookException');
        } catch (RejectWebhookException $e) {
            self::assertSame(406, $e->getStatusCode());
        }
    }

    public function testIntegerPayloadIdBecomesTheRemoteEventId(): void
    {
        $remoteEvent = $this->parse($this->signedRequest(['id' => 123]));

        self::assertInstanceOf(RemoteEvent::class, $remoteEvent);
        self::assertSame('123', $remoteEvent->getId());
    }

    public function testRemoteEventIdIsEmptyWhenThePayloadIdIsNotAStringOrInteger(): void
    {
        $remoteEvent = $this->parse($this->signedRequest(['id' => ['nested' => 'value']]));

        self::assertInstanceOf(RemoteEvent::class, $remoteEvent);
        self::assertSame('', $remoteEvent->getId());
    }

    public function testRemoteEventIdIsEmptyWhenThePayloadHasNoId(): void
    {
        $remoteEvent = $this->parse($this->signedRequest(['type' => 'payment.completed']));

        self::assertInstanceOf(RemoteEvent::class, $remoteEvent);
        self::assertSame('', $remoteEvent->getId());
    }

    /** @param array<string, mixed> $payload */
    private function signedRequest(array $payload, string $method = 'POST'): Request
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return Request::create(
            uri: '/webhook',
            method: $method,
            server: ['HTTP_X_WEBHOOK_SIGNATURE' => 'sha256='.hash_hmac('sha256', $body, self::SECRET)],
            content: $body,
        );
    }

    /** @return null|array<RemoteEvent>|RemoteEvent */
    private function parse(Request $request): array|RemoteEvent|null
    {
        $parser = new TestWebhookRequestParser(
            new HmacSha256SignatureVerifier(self::SIGNATURE_HEADER, 'sha256='),
            self::SECRET,
        );

        return $parser->parse($request, self::SECRET);
    }
}

/**
 * Test webhook request parser implementation.
 */
final class TestWebhookRequestParser extends IntegrationWebhookRequestParser
{
    public function __construct(
        private HmacSha256SignatureVerifier $verifier,
        private string $secret,
    ) {}

    public function getDefinition(): string
    {
        return 'payment.completed';
    }

    public function getMapper(): AbstractWebhookMapper
    {
        return new TestWebhookMapper();
    }

    protected function getSignatureVerifier(): HmacSha256SignatureVerifier
    {
        return $this->verifier;
    }

    protected function getSignatureSecret(): string
    {
        return $this->secret;
    }
}

/**
 * Test webhook event for parser testing.
 */
final class TestParserWebhookEvent implements WebhookEventInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
    ) {}
}

/**
 * Test mapper for parser testing.
 */
final class TestWebhookMapper extends AbstractWebhookMapper
{
    public function getDefinition(): string
    {
        return 'payment.completed';
    }

    public function map(array $payload, array $headers): WebhookEventInterface
    {
        /** @var array{id: string, type: string} $payload */
        return new TestParserWebhookEvent(
            id: $payload['id'],
            type: $payload['type'],
        );
    }
}
