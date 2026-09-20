<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Webhook;

use IntegrationEngine\Infrastructure\Webhook\WebhookFingerprinter;
use IntegrationEngine\Infrastructure\Webhook\WebhookIdempotencyService;
use IntegrationEngine\Tests\Fake\WebhookIdempotencyAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for webhook idempotency and replay detection.
 *
 * Verifies that duplicate webhooks are detected and silently skipped,
 * and that out-of-order replays are handled correctly.
 *
 * @author Carlos Gude
 */
final class WebhookIdempotencyTest extends TestCase
{
    private WebhookIdempotencyAdapter $adapter;
    private WebhookFingerprinter $fingerprinter;
    private WebhookIdempotencyService $service;

    protected function setUp(): void
    {
        $this->adapter = new WebhookIdempotencyAdapter();
        $this->fingerprinter = new WebhookFingerprinter();
        $this->service = new WebhookIdempotencyService($this->adapter, $this->fingerprinter);
    }

    public function testFirstWebhookIsNotDuplicate(): void
    {
        $eventType = 'products/update';
        $payload = ['id' => 123, 'title' => 'Product'];
        $timestamp = new \DateTimeImmutable('2026-09-18T10:00:00Z');

        self::assertFalse($this->service->isDuplicate($eventType, $payload, $timestamp));
    }

    public function testSecondIdenticalWebhookIsDuplicate(): void
    {
        $eventType = 'products/update';
        $payload = ['id' => 123, 'title' => 'Product'];
        $timestamp = new \DateTimeImmutable('2026-09-18T10:00:00Z');

        // First webhook
        self::assertFalse($this->service->isDuplicate($eventType, $payload, $timestamp));
        $this->service->recordProcessed($eventType, $payload, $timestamp);

        // Second identical webhook
        self::assertTrue($this->service->isDuplicate($eventType, $payload, $timestamp));
    }

    public function testDifferentPayloadIsNotDuplicate(): void
    {
        $eventType = 'products/update';
        $timestamp = new \DateTimeImmutable('2026-09-18T10:00:00Z');

        $payload1 = ['id' => 123, 'title' => 'Product A'];
        $payload2 = ['id' => 123, 'title' => 'Product B'];

        // First webhook
        self::assertFalse($this->service->isDuplicate($eventType, $payload1, $timestamp));
        $this->service->recordProcessed($eventType, $payload1, $timestamp);

        // Second webhook with different payload
        self::assertFalse($this->service->isDuplicate($eventType, $payload2, $timestamp));
    }

    public function testDifferentTimestampIsNotDuplicate(): void
    {
        $eventType = 'products/update';
        $payload = ['id' => 123, 'title' => 'Product'];

        $timestamp1 = new \DateTimeImmutable('2026-09-18T10:00:00Z');
        $timestamp2 = new \DateTimeImmutable('2026-09-18T10:00:01Z');

        // First webhook
        self::assertFalse($this->service->isDuplicate($eventType, $payload, $timestamp1));
        $this->service->recordProcessed($eventType, $payload, $timestamp1);

        // Second webhook with different timestamp
        self::assertFalse($this->service->isDuplicate($eventType, $payload, $timestamp2));
    }

    public function testPayloadOrderDoesntAffectDuplicate(): void
    {
        $eventType = 'products/update';
        $timestamp = new \DateTimeImmutable('2026-09-18T10:00:00Z');

        // Same payload, different order in array
        $payload1 = ['id' => 123, 'title' => 'Product', 'vendor' => 'Acme'];
        $payload2 = ['vendor' => 'Acme', 'id' => 123, 'title' => 'Product'];

        // First webhook
        self::assertFalse($this->service->isDuplicate($eventType, $payload1, $timestamp));
        $this->service->recordProcessed($eventType, $payload1, $timestamp);

        // Second webhook (payload order different but content same)
        self::assertTrue($this->service->isDuplicate($eventType, $payload2, $timestamp));
    }

    public function testCleanupRemovesOldFingerprints(): void
    {
        $eventType = 'products/update';
        $payload = ['id' => 123];

        // Record a webhook with a timestamp 2 days ago
        $oldTimestamp = new \DateTimeImmutable('2026-09-16T10:00:00Z');
        $this->service->recordProcessed($eventType, $payload, $oldTimestamp);

        // Verify it's marked as processed
        self::assertTrue($this->service->isDuplicate($eventType, $payload, $oldTimestamp));

        // Run cleanup with 24h retention (default)
        $this->service->cleanup();

        // Fingerprint should now be gone
        self::assertFalse($this->service->isDuplicate($eventType, $payload, $oldTimestamp));
    }

    public function testCleanupKeepsRecentFingerprints(): void
    {
        $eventType = 'products/update';
        $payload = ['id' => 123];

        // Record a webhook well inside the retention window. The adapter's
        // cleanup() cuts off against the real clock, so a fixed date would
        // start failing the moment it falls outside that window.
        $now = new \DateTimeImmutable('-1 hour');
        $this->service->recordProcessed($eventType, $payload, $now);

        // Verify it's marked as processed
        self::assertTrue($this->service->isDuplicate($eventType, $payload, $now));

        // Run cleanup with 24h retention
        $this->service->cleanup();

        // Fingerprint should still be there (recent)
        self::assertTrue($this->service->isDuplicate($eventType, $payload, $now));
    }

    public function testMultipleDuplicatesAreDetected(): void
    {
        $eventType = 'orders/create';
        $payload = ['id' => 999, 'total' => '100.00'];
        $timestamp = new \DateTimeImmutable('2026-09-18T11:00:00Z');

        // First webhook
        self::assertFalse($this->service->isDuplicate($eventType, $payload, $timestamp));
        $this->service->recordProcessed($eventType, $payload, $timestamp);

        // Second through fifth (all duplicates)
        for ($i = 0; $i < 4; ++$i) {
            $attempt = $i + 2;
            self::assertTrue(
                $this->service->isDuplicate($eventType, $payload, $timestamp),
                "Attempt {$attempt} should be detected as duplicate",
            );
        }
    }

    public function testDifferentEventTypesAreNotDuplicates(): void
    {
        $payload = ['id' => 123];
        $timestamp = new \DateTimeImmutable('2026-09-18T10:00:00Z');

        $eventType1 = 'products/update';
        $eventType2 = 'products/delete';

        // First event type
        self::assertFalse($this->service->isDuplicate($eventType1, $payload, $timestamp));
        $this->service->recordProcessed($eventType1, $payload, $timestamp);

        // Second event type (different)
        self::assertFalse($this->service->isDuplicate($eventType2, $payload, $timestamp));
    }

    public function testFingerprintFormat(): void
    {
        $eventType = 'products/update';
        $payload = ['id' => 123, 'title' => 'Product'];
        $timestamp = new \DateTimeImmutable('2026-09-18T10:00:00Z');

        $fingerprint = $this->fingerprinter->fingerprint($eventType, $payload, $timestamp);

        // Format: {eventType}:{timestamp}:{hash}
        $parts = explode(':', $fingerprint);
        self::assertCount(3, $parts);
        self::assertSame('products/update', $parts[0]);
        self::assertSame('1789725600', $parts[1]); // Unix timestamp for 2026-09-18T10:00:00Z
        self::assertNotEmpty($parts[2]); // Hash
    }
}
