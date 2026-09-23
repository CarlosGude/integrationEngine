# 0010 · Webhook Idempotency: Deduplication Strategy

- **Status:** Superseded by ADR 0016
- **Date:** 2026-09-17

> **Current state:** ADR 0014 removed vendor/orchestration pieces, and [ADR 0016](./0016-generic-webhooks-and-application-idempotency.md) later removed bundle-owned deduplication entirely. The decision below is historical; v8 exposes provider event IDs and leaves durable idempotency to the application.

## Context

Webhook providers (Stripe, PayPal, etc.) retry failed deliveries, resulting in the same event being received multiple times. Without deduplication, the application could process the same event twice, causing side effects:
- Duplicate charges (business loss + compliance risk)
- Duplicate state transitions (corrupted audit trail)
- Double message dispatch (race conditions in consumers)

This ADR documents where and how to deduplicate, guided by the principle that **webhooks should not block the HTTP response on processing complexity**. The HTTP 202 response must be fast; deduplication happens async in the consumer.

See: `docs/archived/spikes/webhooks.md` for the spike investigation.

## Decision

### 1. Deduplication strategy: Consumer layer (not parser)

**The parser layer remains stateless and fast.**
- `IntegrationWebhookRequestParser::parse()` verifies signature, returns RemoteEvent
- Dispatches `ConsumeRemoteEventMessage` to Messenger bus
- HTTP response (202) returns immediately

**The consumer layer handles deduplication.**
- `ConsumeRemoteEventHandler` (or app-specific handler) receives the message
- **Before processing:** query database for `WebhookDelivery` with this webhook ID
  - If `status = 'processed'` → log and return (duplicate, already handled)
  - If `status = 'failed'` → retry the processing (transient failure recovery)
  - If not found → proceed to process
- **After processing:** update `WebhookDelivery.status = 'processed'` atomically with business logic

**Rationale:**
- ✅ HTTP response fast (no DB query in critical path)
- ✅ Allows replay testing (resend webhook → re-process if not marked done)
- ✅ Handles delayed retries (provider waits hours → we still deduplicate correctly)
- ✅ Works with both sync and async transports (Messenger flexibility)
- ✅ At-least-once delivery semantics (app responsibility, not framework)

### 2. Deduplication key: Webhook ID from provider

**Use the provider's unique event/delivery ID** (e.g., `evt_123` from Stripe, `evt_id` from PayPal).

Structure in database:
```sql
CREATE TABLE webhook_delivery (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    integration_name VARCHAR(255) NOT NULL,  -- 'stripe', 'paypal', etc.
    webhook_id VARCHAR(255) NOT NULL,        -- provider's event ID
    webhook_type VARCHAR(255) NOT NULL,      -- 'charge.succeeded', etc.
    event_name VARCHAR(255) NOT NULL,        -- RemoteEvent::getName() (for traceability)
    status VARCHAR(50) NOT NULL,             -- 'received', 'processing', 'processed', 'failed'
    attempts INT DEFAULT 1,
    last_error TEXT,
    received_at DATETIME NOT NULL,
    processed_at DATETIME,
    UNIQUE KEY uk_integration_webhook_id (integration_name, webhook_id)
);
```

**Fallback (if provider has no ID):** `timestamp + sha256(payload)` (e.g., for custom webhooks)

### 3. Idempotency window: Event lifetime

**Store webhook deliveries indefinitely** (or per retention policy).

**Rationale:**
- Stripe retries can span 3+ days
- Other providers may retry over weeks (PayPal does 30 days)
- Storage is cheap; deduplication cost of a DB query >> risk of duplicates

**Cleanup:** Lazy deletion (mark soft-deleted after N days, or archive to cold storage)

### 4. Consumer guarantee: At-least-once with idempotent operations

**The bundle guarantees at-least-once delivery** (Messenger always retries transient errors).

**The application is responsible for idempotent handlers:**
- Creating a subscription? Check if it exists first (UNIQUE constraint on external ID)
- Updating inventory? Use atomic operations (UPDATE ... WHERE id = X AND version = Y)
- Logging an event? Idempotent by webhook ID (no duplicate key → ignore)

**Pattern for consumer handler:**

```php
public function __invoke(ConsumeRemoteEventMessage $message)
{
    $remoteEvent = $message->getEvent();
    
    // 1. Check if already processed
    $delivery = $webhookDeliveryRepository->findByIntegrationAndWebhookId(
        $remoteEvent->getName(),  // or $integration_name from message
        $remoteEvent->getId()
    );
    
    if ($delivery?->isProcessed()) {
        $this->logger->info('Webhook already processed', ['id' => $remoteEvent->getId()]);
        return;  // No error; idempotent ✓
    }
    
    // 2. Process the event (business logic)
    try {
        $this->handleChargeSucceeded($remoteEvent);
        
        // 3. Mark as processed (atomic with business logic if possible)
        if (!$delivery) {
            $delivery = new WebhookDelivery(...);
            $this->webhookDeliveryRepository->save($delivery);
        }
        $delivery->markProcessed();
        $this->webhookDeliveryRepository->flush();
    } catch (\Throwable $e) {
        $this->logger->error('Webhook processing failed', ['error' => $e->getMessage()]);
        throw $e;  // Let Messenger retry
    }
}
```

## Alternatives considered

### 1. Deduplication in the parser (fast-fail)
**Rejected:** Would require a DB query in the HTTP critical path, slowing the response. The 202 response should be instant; processing can be async.

### 2. Exactly-once delivery (distributed transaction)
**Rejected:** Impossible without a 3PC (two-phase commit), which is slow and unreliable. At-least-once + idempotent handler is the standard pattern in event-driven systems.

### 3. Deduplication by Messenger (built-in)
**Rejected:** Messenger doesn't have built-in deduplication (it can't; different handlers have different idempotency requirements). The application must handle it.

### 4. Soft-delete vs. hard-delete webhooks
**Decision:** Keep both (soft-delete for audit trail, hard-delete after retention window).

## Consequences

### Positive
- ✅ HTTP responses are fast (202 immediate)
- ✅ Deduplication is transparent to webhook definition authors (handled in consumer)
- ✅ Allows replay testing (resend same webhook, will be re-processed if not marked done)
- ✅ Handles Stripe's multi-day retries without complexity
- ✅ Works with any Messenger transport (sync, async, RabbitMQ, etc.)
- ✅ Audit trail: every delivery is logged

### Negative
- ⚠️ Requires database table for webhook deliveries (add to migrations)
- ⚠️ Consumer authors must implement `markProcessed()` in handlers (boilerplate)
- ⚠️ Race condition: if two messages arrive simultaneously, both might process before the first updates status
  - *Mitigation:* UNIQUE constraint + UPSERT, or acquire lock before processing
- ⚠️ Requires connection/tenant isolation if multi-tenant (see ADR-0003)

### Testing implications
- ✅ Unit tests: mock `WebhookDeliveryRepository`, verify handler calls `markProcessed()`
- ✅ Integration tests: create webhook deliveries in DB, resend same webhook, verify idempotency
- ✅ Replay tests: Stripe CLI `stripe trigger` same event twice, verify single charge

---

## References

- Related decision: ADR-0009 (Inbound Webhooks)
- Stripe webhook retry policy: https://stripe.com/docs/webhooks/best-practices
- PayPal webhook retries: https://developer.paypal.com/api/webhooks/
- Stripe webhook resilience: https://stripe.com/blog/webhook-resilience-best-practices
- At-least-once delivery: https://en.wikipedia.org/wiki/Exactly_once_delivery
