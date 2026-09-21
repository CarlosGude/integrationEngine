# 0012 · Webhook Idempotency: Deduplication Strategy

- **Status:** Accepted  
- **Date:** 2026-09-18

> **Superseded in part by [ADR 0014](./0014-no-vendor-integrations-in-the-bundle.md).** Still current: the fingerprint strategy lives on in WebhookFingerprinter. The dead-letter pieces around it were removed in 6.0 ([ADR 0014](./0014-no-vendor-integrations-in-the-bundle.md)).

## Context

Webhooks are delivered via HTTP with at-least-once semantics. Network retries, client timeouts, or provider redelivery can cause the same webhook event to be received multiple times. For example:

- Customer clicks "Pay" → Payment webhook sent
- Bundle receives it, but response is slow → Provider retries
- Bundle receives the same webhook again → Double charge risk

The bundle needs a strategy to detect and reject duplicates transparently.

## Problem: What Makes a Webhook "Duplicate"?

Two approaches exist:

1. **App-level deduplication** — App implements idempotency keys, stores in database
   - Pros: App has full control; works for any idempotency scheme
   - Cons: Every consuming app must implement; no shared pattern

2. **Bundle-level fingerprinting** — Bundle hashes the webhook and detects duplicates automatically
   - Pros: Free for all apps; consistent behavior
   - Cons: Fingerprinting is fragile if not designed carefully

This ADR chooses option 2: **bundle-level fingerprinting**.

## Decision

**The bundle detects duplicates using an order-invariant recursive hash of the webhook payload.**

Specifics:

1. **Fingerprint = hash(eventType + timestamp + payload)**
   - `eventType`: e.g., "charge.succeeded"
   - `timestamp`: Webhook timestamp (from provider, not wall-clock)
   - `payload`: Recursive sorted hash (order-invariant)

2. **Order-invariant hashing**
   - Sort payload recursively before hashing
   - Detects duplicates even if provider reorders JSON fields
   - Example: `{"a": 1, "b": 2}` and `{"b": 2, "a": 1}` have the same fingerprint

3. **24-hour retention window**
   - Fingerprints are cached for 24 hours
   - Older webhooks are re-processed (assumed to be stale retries)
   - Window is configurable per integration

4. **Duplicate response behavior**
   - First delivery: Process normally
   - Duplicate within 24h: Return HTTP 202 (success) without re-processing
   - Reason: Webhook was already processed; client should treat as success

## Why Not Event IDs?

Some providers (Stripe) include event IDs: `event.id` or `X-Event-ID` header.

**Not used for deduplication because:**
- Not all providers supply IDs (WooCommerce, PayPal, custom APIs)
- Relying on event IDs makes the bundle provider-specific
- Fingerprinting is provider-agnostic and always works

Event IDs **can** be used for **audit trails** and **replay**, but not for duplicate detection.

## Fingerprinting Example

```
Webhook 1:
  {
    "type": "charge.succeeded",
    "timestamp": 1695312000,
    "data": { "id": "ch_123", "amount": 5000 }
  }
  Fingerprint: sha256("charge.succeeded" + "1695312000" + "amount:5000|id:ch_123")

Webhook 2 (duplicate, reordered):
  {
    "type": "charge.succeeded",
    "timestamp": 1695312000,
    "data": { "amount": 5000, "id": "ch_123" }  // fields reordered
  }
  Fingerprint: sha256("charge.succeeded" + "1695312000" + "amount:5000|id:ch_123")
  → SAME fingerprint → Duplicate detected
```

## Cache Backend

Fingerprints are stored in a PSR-6 cache (configurable):

```yaml
integration_engine:
    webhooks:
        idempotency:
            cache: 'cache.app'  # Default; can use Redis, etc.
            ttl: 86400  # 24 hours
```

Supported backends:
- In-memory (array cache) — for testing
- Redis — for production
- Database — for audit-heavy scenarios
- Memcached — for distributed systems

## Handling Old Webhooks

If a webhook's `timestamp` is older than 24 hours, the bundle re-processes it because:
1. It's extremely unlikely to be a duplicate (original was too old)
2. If it is a duplicate, the app's business logic should handle it (idempotent operations)

This is consistent with HTTP semantics: clients retry indefinitely on transient errors.

## False Positives & Edge Cases

### Different providers, same timestamp
```
Stripe webhook: timestamp: 1695312000
WooCommerce webhook: timestamp: 1695312000
Different payloads, different event types → Different fingerprints → No collision
```

### Webhook payload changes in-flight
```
Webhook sent with: {"amount": 5000, "tax": 0}
Network garbles it: {"amount": 5000}
Different payload hash → Treated as different webhook (re-processed)
```

This is rare and acceptable: the provider's signature verification will reject malformed payloads before fingerprinting.

### Timezone or clock skew
The bundle uses the provider's timestamp, not local time. Drift between provider and bundle doesn't affect deduplication (same timestamp = same fingerprint, regardless of local time).

## Consequences

**Positive:**
- Automatic duplicate detection; no app code required
- Works with any webhook provider (provider-agnostic)
- Order-invariant hashing handles JSON field reordering
- 24-hour window balances safety vs. stale retry reprocessing
- Configurable cache backend for different deployment models

**Negative:**
- Fingerprinting adds ~1–2ms per webhook
- Requires persistent cache (can't be in-process only)
- 24-hour window means very old retries are reprocessed (apps must be idempotent anyway)

## Configuration

Default configuration (recommended for most apps):

```yaml
integration_engine:
    webhooks:
        idempotency:
            cache: 'cache.app'
            ttl: 86400  # 24 hours
            hasher: 'sha256'  # or 'md5'
```

Custom implementation:

```php
// Implement your own
class CustomIdempotencyService implements WebhookIdempotencyPort
{
    public function isDuplicate(string $eventType, int $timestamp, array $payload): bool
    {
        // Your logic
    }
}

// Wire in DI
services:
    App\Infrastructure\Webhooks\CustomIdempotencyService:
        arguments:
            - '@cache.app'
```

## Testing

For testing, use in-memory cache:

```php
$cache = new ArrayAdapter();
$idempotency = new WebhookIdempotencyService($cache, ttl: 3600);

$payload1 = ['charge_id' => 'ch_123', 'amount' => 5000];
$payload2 = ['amount' => 5000, 'charge_id' => 'ch_123'];  // reordered

assert(!$idempotency->isDuplicate('charge.succeeded', $timestamp, $payload1)); // First: not a duplicate
assert($idempotency->isDuplicate('charge.succeeded', $timestamp, $payload2));  // Second: duplicate
```

## References

- [ADR 0010 · Webhook Idempotency: Deduplication Strategy](./0010-webhook-idempotency.md) — Related (earlier version; this supersedes)
- [WEBHOOK.md](../WEBHOOK.md) — Webhook configuration and implementation
- [RFC 9110 · HTTP Semantics](https://tools.ietf.org/html/rfc9110) — At-least-once semantics
