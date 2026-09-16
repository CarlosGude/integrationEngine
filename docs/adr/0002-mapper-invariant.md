# 0002 · Mapper invariant: single mapper per action

- **Status:** Accepted
- **Date:** 2026-03-15

## Context

When transforming a raw HTTP response (body and headers from external API) into a typed DTO, there is a risk of:
1. **Multiple representations** — the same action mapped differently by different parts of the app
2. **Response leakage** — raw API fields (with typos, inconsistent naming, sensitive data) exposed to domain code
3. **Accidental mutations** — code in multiple places shares the mapping logic and diverges over time

## Decision

**Each action has exactly one mapper, validated at runtime.**

The engine enforces this invariant: if a mapper declares `getAction()` as `GetUser`, it can only be used with the `GetUser` action. Any mismatch throws an exception.

## Alternatives considered

1. **Multiple mappers per action**
   - Pros: flexibility for different output formats
   - Cons: violates single responsibility, risk of inconsistency
   - Rejected: one action → one DTO contract

2. **Mapper auto-discovery by naming convention**
   - Pros: no explicit declaration needed
   - Cons: harder to debug if mapper is missing; refactoring is fragile
   - Rejected: explicit declaration catches errors at configuration time

## Consequences

**Positive:**
- Response transformation is centralized and predictable
- Easier to audit what data flows from API to domain
- Mapper names clearly signal intent

**Negative:**
- Requires discipline: mapper must be created before the action can be used
- One mapper cannot serve multiple actions (even if they share structure)
  - Workaround: extract common logic to static helpers or traits

## References

- [`AbstractMapper`](../../src/Core/Contract/Mapper/AbstractMapper.php) — base class with `getAction()` contract
- [`tests/Core/AbstractMapperTest.php`](../../tests/Core/AbstractMapperTest.php) — mapper invariant validation
- [`IntegrationEngine::send()`](../../src/Core/IntegrationEngine.php) — invariant check
