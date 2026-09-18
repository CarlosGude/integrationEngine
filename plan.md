# Days 53–60 Outline

**Status**: v5.0.0 complete (inbound webhooks framework). Ready for next phase.

---

## Day 53: Webhook Event Discovery & Registry
**Goal**: Reverse-engineer webhook payload shapes from live Shopify API docs.

- [ ] Document Shopify webhook event types (Products, Orders, Customers, Inventory, etc.)
- [ ] Create `WebhookEventRegistry` — maps event type → expected payload DTO class
- [ ] Stub 3–4 real event DTOs (ProductUpdated, OrderCreated, CustomerUpdated)
- [ ] Integration test: parse a Shopify webhook payload → mapped DTO

**Deliverable**: Registry + 3 event DTOs + passing test

---

## Day 54: Shopify Webhook Ingestion Endpoint
**Goal**: Build the HTTP endpoint that receives and validates Shopify webhooks.

- [ ] Create Symfony controller: `POST /webhooks/shopify`
- [ ] Use `IntegrationWebhookRequestParser` to validate signature
- [ ] Dispatch domain event (e.g., `ShopifyProductUpdated`) from webhook payload
- [ ] Functional test: mock Shopify signature, POST a payload, assert domain event fired

**Deliverable**: Controller + signature validation + functional test

---

## Day 55: Idempotency & Replay Protection
**Goal**: Ensure duplicate webhooks don't cause duplicate side effects.

- [ ] Store webhook fingerprints (event type + timestamp + hash) in DB
- [ ] Check before processing; skip if already seen (within 24h window)
- [ ] Add test: send same payload twice → second is silently ignored
- [ ] Add test: out-of-order replay (old timestamp) → rejected

**Deliverable**: Idempotency middleware + tests

---

## Day 56: Webhook Dead-Letter Queue & Retry
**Goal**: Capture failed webhook processing; enable manual/automatic replay.

- [ ] Create `WebhookFailure` entity: event type, payload, error, created_at, retry_count
- [ ] Async job (Messenger): try processing, catch exceptions → store in DLQ
- [ ] CLI command: `webhook:retry <failure-id>` to manually replay
- [ ] CLI command: `webhook:retry --all-failed` to bulk retry (e.g., after a fix deploy)

**Deliverable**: DLQ entity + Messenger handler + CLI commands

---

## Day 57: Webhook State Machine (optional fast-track)
**Goal**: Track webhook lifecycle for audit/debugging.

- [ ] Add `WebhookEvent` states: received → validating → processing → success/failed
- [ ] Store state transitions in DB (audit trail)
- [ ] Expose read-only state query endpoint: `GET /webhooks/<id>/state`
- [ ] Test: assert state transitions in the happy path and error cases

**Deliverable**: State machine + audit trail + query endpoint

*Note: This can be deferred to Day 58 if Day 56 takes longer.*

---

## Day 58: WooCommerce Webhook Support
**Goal**: Extend webhook framework to a second platform (prove generalization).

- [ ] Document WooCommerce webhook signatures (X-WC-Webhook-ID, X-WC-Webhook-Signature)
- [ ] Implement `WooCommerceWebhookVerifier` (different signature algorithm)
- [ ] Stub 2–3 WooCommerce event DTOs (ProductUpdated, OrderCreated)
- [ ] Functional test: WooCommerce signature validation + payload parsing

**Deliverable**: WooCommerce verifier + event DTOs + passing test

---

## Day 59: Multi-Platform Webhook Router
**Goal**: Route inbound webhooks to the right verifier + processor (by path or header).

- [ ] Refactor controller into generic `POST /webhooks` with routing middleware
- [ ] Router: detect platform by:
  - Path: `/webhooks/shopify` vs `/webhooks/woocommerce`
  - OR header (e.g., `X-Platform: shopify`)
- [ ] Each platform carries its own verifier + event registry
- [ ] Test: single endpoint, two platforms, correct signature validation per platform

**Deliverable**: Generic webhook router + multi-platform tests

---

## Day 60: Documentation & Release Prep (v5.1.0)
**Goal**: Doc + final polish before v5.1.0 tag.

- [ ] Write webhook user guide (WEBHOOK.md)
  - How to create an event DTO
  - How to register a new platform (verifier + events)
  - How to consume domain events
  - Debugging: DLQ, state machine, audit trail
- [ ] Update README with v5.1.0 features
- [ ] Run full test suite + mutation suite
- [ ] Tag v5.1.0; update CHANGELOG

**Deliverable**: Complete docs + passing CI + v5.1.0 tag

---

## Summary

| Phase | Days | Goal |
|-------|------|------|
| **Discovery & Endpoint** | 53–54 | Shopify webhooks ingestion |
| **Reliability** | 55–56 | Idempotency + DLQ + retry |
| **Generalization** | 57–59 | Multi-platform (WooCommerce), router |
| **Release** | 60 | Docs + v5.1.0 tag |

**Risks**: Day 57 (state machine) is optional if bandwidth tightens. Prioritize Day 55–56 (reliability) over Day 57 (audit trail).

