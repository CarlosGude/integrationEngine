# 0008 · No bundle-owned Messenger bridge

- **Status:** Accepted — restored by ADR 0014 after being superseded by ADR 0011
- **Date:** 2026-09-11

## Context

When receiving webhooks or handling errors, applications often need to defer work to a queue (Messenger, RabbitMQ, etc.). The bundle could offer a convenience:
- `dispatch()` method that automatically queues webhook events
- Automatic retry on transient errors using Messenger's retry policy

This seems helpful: "integration errors? Queue them automatically." However, it conflates two concerns:
1. **What** to do when a webhook arrives (business logic: update inventory, charge card)
2. **When** to do it (immediately, queued, scheduled)

## Decision

**The bundle does not own a Messenger message/handler/retry abstraction. Delivery and queue policy remain the consuming application's responsibility.**

Instead:

- the bundle parses, verifies and maps a webhook to `MappedRemoteEvent`;
- Symfony's standard Webhook controller may use Messenger/`ConsumeRemoteEventMessage` as its transport mechanism;
- the bundle does not add a second message type, handler, retry policy or DLQ abstraction;
- an application using a custom controller/transport may choose another delivery mechanism.

This keeps the bundle focused on **what arrives** (integration mechanics), not **when it's processed** (application deployment model).

## Alternatives considered

1. **Built-in Messenger dispatcher**
   - Pros: convenient for apps using Messenger
   - Cons: mandatory for Webhook support; doesn't fit apps using other queues (Redis, Amazon SQS); complicates testing
   - Rejected: violates the "bundle is transport, not orchestration" principle

2. **Event system where apps listen and dispatch**
   - Pros: decoupled; apps choose how to handle
   - Cons: requires PSR-14 EventDispatcher setup
   - Rejected: overly complex; just return data and let app decide

## Consequences

**Positive:**
- Bundle stays focused on webhook parsing and verification
- App can use any queue system (Messenger, custom, Redis, etc.)
- App explicitly controls the workflow
- Easier to test (no queue stubbing in bundle tests)

**Negative:**
- Queue routing, retry and failure-transport policy live in the consuming Symfony application
- Applications not using Symfony's standard webhook controller own their delivery mechanism explicitly

## References

- [ARCHITECTURE.md](../ARCHITECTURE.md) — design philosophy
- Webhook support and its Messenger-agnostic event system added in v4.4
