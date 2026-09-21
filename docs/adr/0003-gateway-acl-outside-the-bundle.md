# 0003 · Gateway and access control belong to the application

- **Status:** Accepted
- **Date:** 2026-03-15

## Context

After mapping an external API response to a DTO, the application must decide:
1. **What to expose** — which fields are relevant to the domain?
2. **Who can see it** — is the current user authorized to see this data?
3. **How to cache it** — should the domain layer cache this result?

The bundle's responsibility is to deliver the DTO. What happens next is the application's concern.

## Decision

**The bundle is transport and schema; the application owns access control.**

- `ResponseInterface` (DTO) represents the external API shape, not domain objects
- Application layer creates a `Gateway` that:
  - Calls the engine (`send()`)
  - Filters/transforms DTOs into domain objects
  - Enforces authorization (ACL)
  - Manages domain-level caching

The bundle **never** checks authorization or filters responses.

## Alternatives considered

1. **ACL plugins in the bundle**
   - Pros: centralized policy
   - Cons: bundle becomes tightly coupled to auth strategies; harder to audit per-request decisions
   - Rejected: authorization is domain-specific

2. **Automatic DTO caching in the bundle**
   - Pros: transparent to caller
   - Cons: TTLs are app-specific; conflicts with domain cache strategies
   - Rejected: bundle handles HTTP cache only; domain layer decides persistence

## Consequences

**Positive:**
- Bundle stays focused on integration mechanics
- Authorization logic is visible and auditable in the application
- Easy to enforce different policies per endpoint (public vs. admin API)
- Domain and infrastructure layers remain decoupled

**Negative:**
- Application code must exist before the bundle can be useful
- Developers must remember that `ResponseInterface` is not a domain object

## References

- [`ResponseInterface`](../../src/Core/Contract/Response/ResponseInterface.php) — marker for DTOs
- [ARCHITECTURE.md](../ARCHITECTURE.md) — gateway pattern section
- [`tests/Infrastructure/SymfonyHttpClientAdapterBatchTest.php`](../../tests/Infrastructure/SymfonyHttpClientAdapterBatchTest.php) — DTO contract tests
