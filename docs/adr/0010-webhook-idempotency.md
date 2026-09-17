# 0010 · Webhook Idempotency: Deduplication Strategy

- **Status:** Proposed
- **Date:** 2026-09-17

## Context

Webhook providers (Stripe, PayPal, etc.) may retry failed deliveries, resulting in the same event being received multiple times. The application must deduplicate to prevent side effects (duplicate charges, duplicate state transitions, etc.).

This ADR documents the chosen deduplication strategy, including:

1. Where deduplication happens (parser, consumer, repository)
2. Deduplication key (webhook ID, timestamp + hash, etc.)
3. Idempotency guarantees for concurrent retries

See: `docs/spikes/webhooks.md` for the spike investigation.

## Decision

*(To be filled after spike investigation)*

### Deduplication Strategy

- [ ] Implement at parser level (fast-fail on duplicate)
- [ ] Implement at consumer level (allow re-processing, check in handler)
- [ ] Implement at repository level (UNIQUE constraint + ON CONFLICT strategy)

### Deduplication Key

- [ ] Webhook ID from provider (e.g., `evt_1234` from Stripe)
- [ ] Timestamp + payload hash (when no ID available)
- [ ] Connection + event type + key (namespace by tenant)

### Idempotency Window

- [ ] How long to remember delivered webhooks
- [ ] Cleanup strategy (cron, TTL column, soft delete)

### Consumer Guarantee

- [ ] Exactly-once semantic (impossible; settable for idempotent ops)
- [ ] At-least-once + idempotent consumer (preferred)
- [ ] Duplicate detection + replay protection in handler

## Alternatives considered

*(To be filled after spike)*

## Consequences

*(To be filled after spike)*

## References

- Spike: `docs/spikes/webhooks.md`
- Related: ADR-0009 (Inbound Webhooks)
- Stripe webhook retry policy: https://stripe.com/docs/webhooks/best-practices
