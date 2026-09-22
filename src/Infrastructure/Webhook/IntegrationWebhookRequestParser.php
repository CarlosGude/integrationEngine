<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook;

use IntegrationEngine\Core\Contract\Webhook\SignatureVerifierInterface;
use IntegrationEngine\Core\Contract\Webhook\UnknownEventPolicy;
use IntegrationEngine\Core\Contract\Webhook\WebhookDefinition;
use IntegrationEngine\Core\Event\WebhookReceived;
use IntegrationEngine\Core\Event\WebhookRejected;
use IntegrationEngine\Core\Exception\WebhookRejectionReason;
use IntegrationEngine\Core\Exception\WebhookSignatureException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/** Verifies raw bytes before decoding and mapping the authenticated event. */
final class IntegrationWebhookRequestParser extends AbstractRequestParser
{
    public function __construct(
        private readonly WebhookDefinition $definition,
        private readonly SignatureVerifierInterface $verifier,
        private readonly string $integrationName,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new class implements RequestMatcherInterface {
            public function matches(Request $request): bool
            {
                $contentType = strtolower(trim(explode(';', $request->headers->get('Content-Type', ''))[0]));

                return $request->isMethod('POST') && ('application/json' === $contentType || 1 === preg_match('~^application/[a-z0-9.!#$&^_+-]+\+json$~', $contentType));
            }
        };
    }

    protected function validate(Request $request): void
    {
        if (!$this->getRequestMatcher()->matches($request)) {
            $this->reject(WebhookRejectionReason::PayloadInvalid);
        }
    }

    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): ?RemoteEvent
    {
        // YAML is the single signing-secret source. Symfony's routing secret is
        // intentionally unused; applications leave it empty in framework config.
        $raw = $request->getContent();
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = array_map(static fn (?string $value): string => $value ?? '', $values);
        }

        try {
            $this->verifier->verify($raw, $headers, $this->definition->signature);
        } catch (WebhookSignatureException $error) {
            $this->reject($error->reason());
        }

        try {
            $payload = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->reject(WebhookRejectionReason::PayloadInvalid);
        }
        if (!\is_array($payload) || !str_starts_with(ltrim($raw), '{')) {
            $this->reject(WebhookRejectionReason::PayloadInvalid);
        }

        $type = DotPath::get($payload, $this->definition->typeField);
        $id = DotPath::get($payload, $this->definition->idField);
        if (!\is_string($type) || '' === $type || (!\is_string($id) && !\is_int($id)) || '' === $id) {
            $this->reject(WebhookRejectionReason::PayloadInvalid);
        }
        $mapper = $this->definition->mapperFor($type);
        if (null === $mapper) {
            if (UnknownEventPolicy::Ignore === $this->definition->unknownEvents) {
                return null;
            }
            $this->reject(WebhookRejectionReason::UnknownEvent);
        }

        $event = new MappedRemoteEvent($type, (string) $id, $payload, $mapper::map($type, $payload, $headers));
        $this->eventDispatcher?->dispatch(new WebhookReceived($this->integrationName, $type, (string) $id, microtime(true)));

        return $event;
    }

    private function reject(WebhookRejectionReason $reason): never
    {
        $this->eventDispatcher?->dispatch(new WebhookRejected($this->integrationName, $reason->value, microtime(true)));
        $previous = new WebhookRejectedException($reason);

        throw new RejectWebhookException(406, $previous->getMessage(), $previous);
    }
}
