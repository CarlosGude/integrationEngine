<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook;

use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\SignatureVerifierInterface;
use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/**
 * Base class for webhook request parsers that verify signatures and map payloads.
 *
 * Extends Symfony's AbstractRequestParser to provide signature verification
 * and payload mapping via our webhook infrastructure.
 *
 * Subclasses must:
 * 1. Implement getDefinition() — return the webhook event type name
 * 2. Implement getMapper() — return the AbstractWebhookMapper for this event type
 * 3. Implement getSignatureVerifier() — return the configured signature verifier
 * 4. Implement getSignatureSecret() — return the secret for signature verification
 *
 * @author Carlos Gude
 */
abstract class IntegrationWebhookRequestParser extends AbstractRequestParser
{
    /**
     * Get the webhook definition this parser handles.
     *
     * @return string Event type (e.g., 'charge.succeeded')
     */
    abstract public function getDefinition(): string;

    /**
     * Get the mapper for this webhook type.
     */
    abstract public function getMapper(): AbstractWebhookMapper;

    /**
     * Get the signature verifier for this webhook.
     */
    abstract protected function getSignatureVerifier(): SignatureVerifierInterface;

    /**
     * Get the signature secret (shared key with provider).
     */
    abstract protected function getSignatureSecret(): string;

    /**
     * Get the request matcher for validating incoming webhook requests.
     *
     * Override to add custom validation (e.g., specific headers, Content-Type).
     */
    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new MethodRequestMatcher('POST'),
            new IsJsonRequestMatcher(),
        ]);
    }

    /**
     * Parse and verify a webhook request.
     *
     * Verifies the request signature using the configured verifier,
     * then maps the payload to a RemoteEvent using the configured mapper.
     *
     * @param Request $request The incoming webhook request
     *
     * @return null|RemoteEvent The parsed event, or null to silently ignore
     *
     * @throws RejectWebhookException If signature verification fails or payload is malformed
     */
    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): ?RemoteEvent
    {
        // Symfony passes framework.webhook.routing.<type>.secret, which may be
        // left empty: fall back to the parser's own secret, and never verify
        // with an empty key — anyone can compute an HMAC with it.
        if ('' === $secret) {
            $secret = $this->getSignatureSecret();
        }

        if ('' === $secret) {
            throw new RejectWebhookException(
                statusCode: 406,
                message: 'No webhook signing secret configured',
            );
        }

        $body = $request->getContent();
        $verifier = $this->getSignatureVerifier();
        $signatureHeader = $verifier->getHeaderName();
        $signature = $request->headers->get($signatureHeader);

        if (null === $signature) {
            throw new RejectWebhookException(
                statusCode: 406,
                message: "Missing signature header: {$signatureHeader}",
            );
        }

        if (!$verifier->verify($body, $signature, $secret)) {
            throw new RejectWebhookException(
                statusCode: 406,
                message: 'Signature verification failed',
            );
        }

        try {
            $payload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RejectWebhookException(
                statusCode: 406,
                message: "Malformed JSON payload: {$e->getMessage()}",
            );
        }

        if (!\is_array($payload)) {
            throw new RejectWebhookException(
                statusCode: 406,
                message: 'Payload must be a JSON object',
            );
        }

        /** @var array<string, mixed> $payload */
        $headers = [];
        foreach ($request->headers->all() as $key => $headerValues) {
            $headers[$key] = reset($headerValues) ?: null;
        }

        $mapper = $this->getMapper();
        $mapper->map($payload, $headers);

        $id = '';
        if (isset($payload['id']) && (\is_string($payload['id']) || \is_int($payload['id']))) {
            $id = (string) $payload['id'];
        }

        return new RemoteEvent(
            name: $this->getDefinition(),
            id: $id,
            payload: $payload,
        );
    }
}
