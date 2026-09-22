# Architecture Decision Records (ADRs)

This directory contains decisions about the design of IntegrationEngine, recorded in the format described by [Michael Nygard](https://thinkrelevant.com/blog/2011/11/15/documenting-architecture-decisions/).

Each ADR documents:
- **Context:** Why this decision was needed
- **Decision:** What was chosen and why
- **Alternatives considered:** What was rejected and why
- **Consequences:** Positive and negative effects

## Index

| # | Title | Status |
|---|---|---|
| [0001](0001-stateless-actions-and-declarative-yaml.md) | Stateless actions and declarative YAML configuration | Accepted |
| [0002](0002-mapper-invariant.md) | Mapper invariant: single mapper per action | Accepted |
| [0003](0003-gateway-acl-outside-the-bundle.md) | Gateway and access control belong to the application | Accepted |
| [0004](0004-concurrency-with-lazy-http-responses.md) | Concurrency with lazy HTTP responses | Accepted |
| [0005](0005-token-cache-per-connection-and-single-retry-on-401.md) | Token cache per connection and single retry on 401 | Accepted |
| [0006](0006-profiler-never-records-secrets.md) | Profiler never records secrets | Accepted |
| [0007](0007-versioning-policy-after-v4.md) | Versioning policy after v4 | Accepted |
| [0008](0008-no-messenger-bridge-in-the-bundle.md) | No Messenger bridge in the bundle | Accepted |
| [0015](0015-resilience-classification-boundary.md) | Keep Symfony error classification outside Core | Accepted |

## How to Read These

Each ADR is short (1 page) and self-contained. Start with the decision that interests you; context and alternatives help you understand the trade-offs.

## When to Write a New ADR

Write a new ADR when you're making a significant architectural decision that:
- Affects multiple parts of the bundle
- Involves a trade-off (pros and cons)
- Might be questioned later (justifying it now saves future explanations)

## References in Code

ADRs link to relevant code and tests. Use these to understand how each decision is implemented.
