# IntegrationEngine · Roadmap

Public roadmap: what's live, what's next, and what stays out of scope.

---

## Recently shipped

- **v4.1.0** — Unified quality gates (MSI 85/95%, PHPStan max, CI matrix).
- **v4.0.0** — Middleware pipeline, request middleware (OAuth 1.0a signing), connection resolver, per-action timeout, path resolution from request body.
- **v3.0.0** — Batch isolation, `BatchResultCollection`, `AbstractBatchMapper`, action-level mapper validation.

---

## Now

**Phase 1: Presentable** — Public state, roadmap, landing corrections, v4.1.1 release.

Publicly visible status so recruiters and tech leads understand the project state without promises attached to dates.

---

## Next

**Phase 2: Demo online** — Live `demo.integrationengine.dev` showing a real TMDB + Stripe integration tour.

Runway: 22 days. Tour walkthrough showing the problem/solution pattern, parallel execution, extension points, supplier failures, and bidirectional webhooks.

**Phase 3: Integressions robustas** — Form-encoded bodies, GraphQL support, resiliency (timeout, retry, 401 recovery).

Runway: 13 days. Real-world integrations (Shopify, Stripe, legacy gateways) often need schema negotiation, partial failures, and smart retries.

**Phase 4: Webhooks and async** — Inbound webhook definitions, signature verification, Messenger integration, idempotent consumers.

Runway: 29 days. Completing the bidirectional story: outbound requests (Phases 1-3) plus inbound events (webhooks, RabbitMQ, deduplication).

**Phase 5: Quality of design visible** — PHPStan rule extensions, SSRF protection, lifecycle events.

Runway: 16 days. Making the design decisions visible as executable guarantees: no SSRF escapes, no untyped arrays leaking, observable lifecycle.

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
