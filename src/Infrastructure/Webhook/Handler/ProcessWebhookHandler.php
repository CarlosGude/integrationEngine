<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook\Handler;

use IntegrationEngine\Core\Contract\Webhook\WebhookDlqPort;
use IntegrationEngine\Core\Contract\Webhook\WebhookMapperResolverPort;
use IntegrationEngine\Core\Webhook\WebhookFailure;
use IntegrationEngine\Infrastructure\Webhook\Message\ProcessWebhookMessage;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles webhook processing with automatic DLQ fallback.
 *
 * Attempts to process the webhook via mapping and dispatching as domain event.
 * If processing fails, stores the failure in the DLQ for later retry.
 *
 * @author Carlos Gude
 */
#[AsMessageHandler]
final class ProcessWebhookHandler
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private WebhookMapperResolverPort $mapperResolver,
        private WebhookDlqPort $dlq,
    ) {}

    /**
     * Process a webhook message.
     *
     * @param ProcessWebhookMessage $message The webhook to process
     */
    public function __invoke(ProcessWebhookMessage $message): void
    {
        try {
            $mapper = $this->mapperResolver->resolveMapper($message->eventType);
            if (null === $mapper) {
                return;
            }

            $domainEvent = $mapper->map($message->payload, $message->headers);

            $this->eventDispatcher->dispatch($domainEvent);
        } catch (\Throwable $error) {
            $failureId = $this->generateFailureId();
            $failure = WebhookFailure::fromThrowable(
                $failureId,
                $message->eventType,
                $message->payload,
                $error,
            );

            $this->dlq->store($failure);
        }
    }

    /**
     * RFC 4122 v4 UUID from a CSPRNG, so failure ids are unpredictable and collision-safe.
     */
    private function generateFailureId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = \chr((\ord($bytes[6]) & 0x0F) | 0x40); // version 4
        $bytes[8] = \chr((\ord($bytes[8]) & 0x3F) | 0x80); // RFC 4122 variant

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
