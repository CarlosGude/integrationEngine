# 0008 · No Messenger bridge in the bundle

- **Status:** Accepted
- **Date:** 2026-09-11

## Context

When receiving webhooks or handling errors, applications often need to defer work to a queue (Messenger, RabbitMQ, etc.). The bundle could offer a convenience:
- `dispatch()` method that automatically queues webhook events
- Automatic retry on transient errors using Messenger's retry policy

This seems helpful: "integration errors? Queue them automatically." However, it conflates two concerns:
1. **What** to do when a webhook arrives (business logic: update inventory, charge card)
2. **When** to do it (immediately, queued, scheduled)

## Decision

**The bundle does not include Messenger integration. Webhooks are passed to the application; queuing is the app's choice.**

Instead:
- The bundle parses and verifies webhooks
- Returns a typed `WebhookEvent` to the application
- The application decides: handle immediately or queue?

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
- Apps using Messenger must write their own listener and dispatcher
- No built-in retry mechanism (app must implement or use Messenger's)
- More boilerplate in the consuming app

## References

- [ARCHITECTURE.md](../../ARCHITECTURE.md) — design philosophy
- Webhook support and its Messenger-agnostic event system added in v4.4
