<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook;

/**
 * Generates fingerprints for webhook idempotency detection.
 *
 * Combines event type, timestamp, and payload hash to create
 * a unique identifier for each webhook.
 *
 * Format: `{eventType}:{timestamp}:{hash}`
 *
 * @author Carlos Gude
 */
final class WebhookFingerprinter
{
    /**
     * Generate a fingerprint for a webhook event.
     *
     * @param string               $eventType Event type (e.g., 'products/update')
     * @param array<string, mixed> $payload   The webhook payload
     * @param \DateTimeImmutable   $timestamp When the webhook was received
     *
     * @return string Fingerprint in format `{eventType}:{timestamp}:{hash}`
     */
    public function fingerprint(
        string $eventType,
        array $payload,
        \DateTimeImmutable $timestamp,
    ): string {

        $sortedPayload = $this->sortArrayRecursively($payload);
        $payloadJson = json_encode($sortedPayload, JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $payloadJson);


        $timestampStr = $timestamp->format('U');

        return "{$eventType}:{$timestampStr}:{$hash}";
    }

    /**
     * Recursively sort array keys to ensure consistent ordering.
     *
     * @param array<mixed, mixed> $array
     *
     * @return array<mixed, mixed>
     */
    private function sortArrayRecursively(array $array): array
    {
        ksort($array);

        foreach ($array as $key => $value) {
            if (\is_array($value)) {
                $array[$key] = $this->sortArrayRecursively($value);
            }
        }

        return $array;
    }
}
