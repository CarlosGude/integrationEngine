# Architecture Decision Records

ADRs preserve decisions in their historical context. A superseded ADR is intentionally kept: it explains how the design evolved but is not a current API reference. For current behavior, start with [documentation.md](../documentation.md) and [architecture.md](../architecture.md).

| # | Decision | Status |
|---|---|---|
| [0001](0001-stateless-actions-and-declarative-yaml.md) | Stateless actions and declarative YAML | Accepted |
| [0002](0002-mapper-invariant.md) | Mapper invariant | Accepted |
| [0003](0003-gateway-acl-outside-the-bundle.md) | Gateway and ACL outside the bundle | Accepted |
| [0004](0004-concurrency-with-lazy-http-responses.md) | Concurrency with lazy HTTP responses | Accepted |
| [0005](0005-token-cache-per-connection-and-single-retry-on-401.md) | Token cache per connection; one retry on rejected cached token | Accepted |
| [0006](0006-profiler-never-records-secrets.md) | Profiler never records secrets | Accepted |
| [0007](0007-versioning-policy-after-v4.md) | Versioning policy after v4 | Accepted |
| [0008](0008-no-messenger-bridge-in-the-bundle.md) | No bundle-owned Messenger bridge | Accepted |
| [0009](0009-inbound-webhooks.md) | Inbound webhook parser contract | Superseded |
| [0010](0010-webhook-idempotency.md) | Webhook idempotency in the bundle | Superseded |
| [0011](0011-messenger-support-for-webhooks.md) | Messenger support for webhooks | Superseded |
| [0012](0012-webhook-idempotency-strategy.md) | Bundle-level webhook fingerprinting | Superseded |
| [0013](0013-intermediate-timing-events.md) | Intermediate timing events | Superseded |
| [0014](0014-no-vendor-integrations-in-the-bundle.md) | No vendor integrations in the bundle | Accepted |
| [0015](0015-resilience-classification-boundary.md) | Resilience classification boundary | Accepted |
| [0016](0016-generic-webhooks-and-application-idempotency.md) | Generic authenticated webhooks and application idempotency | Accepted |
| [0017](0017-phpstan-contracts.md) | PHPStan contracts without speculative send inference | Accepted |
| [0018](0018-body-type-invariant.md) | Body type invariant, validated at config time | Accepted |
| [0019](0019-profiler-payload-visibility.md) | Opt-in payload visibility in the profiler | Accepted |
| [0020](0020-shared-default-authorization.md) | Shared `defaults.authorization` with per-action override | Accepted |

Status values: `Accepted` (current), `Superseded` (replaced — see the ADR's own status line for the successor), `Retired` (no longer applicable, no successor).

## Adding an ADR

Copy [0000-template.md](0000-template.md), allocate the next number and record the decision rather than duplicating usage documentation. If a later decision replaces it, keep the old ADR and update its status/supersession note.