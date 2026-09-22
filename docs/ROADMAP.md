# IntegrationEngine · Roadmap

Public roadmap: what's live, what's next, and what stays out of scope.

---

## Recently shipped

- **v7.0.0** — Per-integration client registration (simpler config), middleware pipeline via tagged services with priority, `RequestMiddlewareInterface` for request signing (OAuth 1.0a, AWS SigV4), connection resolver for multi-tenant support, path resolution from request body, FormEncodedClientAdapter, PHP 8.4 compatibility, 100% mutation testing.
- **v6.0.0** — Webhooks pared back to what is provider-agnostic: three signature schemes, the parser and mapper contracts, the consumer trait, idempotency. The vendor integrations and the unused reliability scaffolding are gone ([ADR 0014](./adr/0014-no-vendor-integrations-in-the-bundle.md)).
- **v5.2.0–v5.4.0** — Lifecycle events with separate HTTP and mapping timings, observability helpers, Symfony Flex recipe.
- **v5.1.0** — Inbound webhooks: signature verification and duplicate detection by payload fingerprint.
- **v5.0.0** — Webhook framework foundation, signature verification (HMAC, timestamped), `AbstractWebhookMapper`, `IntegrationWebhookRequestParser`, YAML webhook config.
- **v4.1.0** — Unified quality gates (MSI 85/95%, PHPStan max, CI matrix).
- **v3.0.0** — Batch isolation, `BatchResultCollection`, `AbstractBatchMapper`, action-level mapper validation.

---

## Now

Implemented locally for the next release:

- Behavioral tests for GraphQL batches, form-encoded requests, observability,
  logging and resilience utilities; fixes for bugs exposed by those tests.
- Console inspection through `debug:integration [name]`, including JSON output.

**Phase 5: Quality of design visible** — PHPStan rule extensions, SSRF protection, lifecycle events, observability.

Making the design decisions visible as executable guarantees: no SSRF escapes, no untyped arrays leaking, observable lifecycle.

---

## Next (Post-Phase 5)

**Phase 7: Admin experience** — CLI commands and a debugging surface for what the engine already records.

- Console inspection implemented in Unreleased (`debug:integration [name]`)
- Metrics/observability (Prometheus exports)

Webhook replay and dead letters are not on this list: Symfony's Messenger failure transport already does that, and 6.0 stopped pretending the bundle should.

---

## Later

Post-Phase 5, if the demand exists:

- **Open-source plugins** ecosystem — a curated registry of adapters (SOAP, FTP, message queues).
- **Legacy migration patterns** — strangler fig helpers, test harnesses for incremental adoption.
- **Certified training materials** — video course, design workshop templates.

---

## Out of scope

- **OAuth2 client credentials flow** in the tour (reserved for partner integrations).
- **Letterboxd** (OAuth2 read-only, gated by API approval).
- **Stripe Checkout** with customer card entry (payment intents mode is sufficient for the tour).
- **Legacy migration** step-by-step (strangler fig, post-Phase 5).
- **Messenger bridge** inside the bundle (belongs in consuming apps, not the engine).
- **Open-source plugins** ecosystem (one repo, one vision; users fork or build their own adapters).

---

## Why no dates

Each day is 1–2 hours of focused work. Roadmap assumes 3–5 days per week. Slippage happens: API changes, discovered bugs, unexpected scope. Public dates would be promises we can't keep. Instead, **phase by phase becomes shipping milestones** — when Phase 2 lands, that's the signal.

Transparency comes from **code, tests, and running software** — not calendars.
