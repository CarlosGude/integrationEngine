# 0007 · Versioning policy after v4

- **Status:** Accepted
- **Date:** 2026-09-11

## Context

The bundle's version history shows four major versions:
- v1.x: early iterations, learning from production use
- v2.x: stable API, multi-request support, dynamic auth
- v3.x: batch isolation, middleware pipeline introduction, core contracts reorganized
- v4.x: middleware architecture finalized, multi-tenancy, request middleware

Each major marked a shift in design or breaking API change. Going forward, we need a clear versioning policy.

## Decision

**Semantic Versioning with deliberate major bumps for design clarity.**

- **MAJOR** (`X.0.0`): Breaking API changes (middleware base class, interface renames, removed public methods)
  - Only when the bundle's public contract changes in ways that require app code updates
  - Example: v3→v4 (middleware decorator→pipeline)

- **MINOR** (`X.Y.0`): Backward-compatible additions (new interfaces, new config options, new middleware)
  - Apps can opt-in to new features without code changes
  - Example: v2.3 (added `DynamicBaseUrlClientInterface`), v4.1 (added Symfony 6.4+ support)

- **PATCH** (`X.Y.Z`): Bug fixes and security patches
  - No API changes, no new features
  - Example: v2.3.1 (fixed `TraceableClient` to implement `DynamicBaseUrlClientInterface`)

## Alternatives considered

1. **Calendar versioning** (2026.01, 2026.02)
   - Pros: timestamp signals recency
   - Cons: doesn't communicate stability; users can't predict breaking changes
   - Rejected: semantic versioning is clearer for a dependency

2. **Always use minor bumps** (never major)
   - Pros: "we're always stable"
   - Cons: apps don't know when to expect code breakage; false stability promise
   - Rejected: honesty about breaking changes is better than false stability

## Consequences

**Positive:**
- Clear signal: major version = review your code; minor = just update; patch = safe
- Dependency management is predictable (`^4.1` guarantees no breaking changes)
- Portfolio clarity: four majors show design evolution, not instability

**Negative:**
- Major bumps create upgrade friction (documentation, migration guides required)
- Some breaking changes might be "easy" but still require a major bump

## References

- [`CONTRIBUTING.md`](../../CONTRIBUTING.md) — versioning policy details
- [`CHANGELOG.md`](../../CHANGELOG.md) — version history with breaking changes documented
- [`UPGRADE-4.0.md`](../UPGRADE-4.0.md) — example of major-version migration guide
