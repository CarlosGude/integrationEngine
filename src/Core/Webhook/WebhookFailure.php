<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Webhook;

/**
 * Represents a failed webhook processing attempt.
 *
 * Stored in the dead-letter queue for manual or automatic retry.
 *
 * @author Carlos Gude
 */
final readonly class WebhookFailure
{
    /**
     * @param string               $id            Unique failure ID (UUID)
     * @param string               $eventType     Webhook event type (e.g., 'products/update')
     * @param array<string, mixed> $payload       The webhook payload that failed
     * @param string               $errorMessage  The error that occurred
     * @param string               $errorClass    Exception class name
     * @param \DateTimeImmutable   $createdAt     When the failure occurred
     * @param int                  $retryCount    How many times this has been retried
     * @param null|string          $lastAttemptAt When the last retry attempt was made
     */
    public function __construct(
        public string $id,
        public string $eventType,
        public array $payload,
        public string $errorMessage,
        public string $errorClass,
        public \DateTimeImmutable $createdAt,
        public int $retryCount = 0,
        public ?string $lastAttemptAt = null,
    ) {}

    /**
     * Create a WebhookFailure from an exception.
     *
     * @param string               $id        Unique failure ID
     * @param string               $eventType Webhook event type
     * @param array<string, mixed> $payload   The webhook payload
     * @param \Throwable           $error     The exception that occurred
     */
    public static function fromThrowable(
        string $id,
        string $eventType,
        array $payload,
        \Throwable $error,
    ): self {
        return new self(
            id: $id,
            eventType: $eventType,
            payload: $payload,
            errorMessage: $error->getMessage(),
            errorClass: $error::class,
            createdAt: new \DateTimeImmutable(),
        );
    }

    /**
     * Create a new retry attempt of this failure.
     *
     * @return self A copy with incremented retry_count and updated lastAttemptAt
     */
    public function withRetry(): self
    {
        return new self(
            id: $this->id,
            eventType: $this->eventType,
            payload: $this->payload,
            errorMessage: $this->errorMessage,
            errorClass: $this->errorClass,
            createdAt: $this->createdAt,
            retryCount: $this->retryCount + 1,
            lastAttemptAt: (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        );
    }
}
