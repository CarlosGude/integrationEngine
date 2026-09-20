# IntegrationEngine · Roadmap

Public roadmap: what's live, what's next, and what stays out of scope.

---

## Recently shipped

- **v5.1.0** — Inbound webhooks: signature verification, and the contracts for idempotency, dead-letter queue and audit trail.
- **v5.0.0** — Webhook framework foundation, signature verification (HMAC, timestamped), `AbstractWebhookMapper`, `IntegrationWebhookRequestParser`, YAML webhook config.
- **v4.1.0** — Unified quality gates (MSI 85/95%, PHPStan max, CI matrix).
- **v4.0.0** — Middleware pipeline, request middleware (OAuth 1.0a signing), connection resolver, per-action timeout, path resolution from request body.
- **v3.0.0** — Batch isolation, `BatchResultCollection`, `AbstractBatchMapper`, action-level mapper validation.

---

## Now

**Phase 5: Quality of design visible** — PHPStan rule extensions, SSRF protection, lifecycle events, observability.

Making the design decisions visible as executable guarantees: no SSRF escapes, no untyped arrays leaking, observable lifecycle. Webhook audit trails provide foundation for compliance + debugging.

---

## Next (Post-Phase 5)

**Phase 7: Admin experience** — CLI commands, dashboard, webhook replay/debugging UI.

- `webhook:list`, `webhook:dlq:list`, `webhook:dlq:retry` commands
- Admin dashboard: webhook history, audit trail, state machine visualization
- Replay UI for failed webhooks
- Metrics/observability (Prometheus exports)

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
