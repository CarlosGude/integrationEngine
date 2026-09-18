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

    private function generateFailureId(): string
    {
        return \sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0x0FFF) | 0x4000,
            mt_rand(0, 0x3FFF) | 0x8000,
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
        );
    }
}
