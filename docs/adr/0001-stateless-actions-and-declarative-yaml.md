# 0001 · Stateless actions and declarative YAML configuration

- **Status:** Accepted
- **Date:** 2026-03-15

## Context

When integrating external APIs, the contract between the bundle and the application service involves:
1. **What goes in** — method, path parameters, request body, headers
2. **What comes out** — typed DTO

Rather than encoding this contract in PHP classes with mutable state (setters, state validation at call time), we chose to declare it once, make it immutable, and delegate I/O concerns to the engine.

## Decision

Each API endpoint is represented by an **immutable `AbstractAction` subclass**:
- Declares HTTP method, path template, auth, mapper, optional body/context interfaces
- No state mutations after creation
- Configuration sourced from YAML, parsed once at bundle compile time

**Alternative considered:** API configuration in PHP attributes or builder pattern.
- **Rejected:** attributes scatter the contract across generated code and are harder to audit; builder pattern encourages mutable intermediate states and validation at call time instead of at configuration load.

## Alternatives considered

1. **Runtime PHP configuration** (builder, attributes)
   - Pros: IDE support, type safety at compile time
   - Cons: contract is mutable, validation is deferred, scattered across code
   - Rejected: violates "fail fast at configuration time" principle

2. **Mutable action objects with setters**
   - Pros: simple to understand
   - Cons: easy to accidentally mutate; threading issues in async environments; complicates testing
   - Rejected: immutability is essential for safe concurrent use

## Consequences

**Positive:**
- Action contract is centralized, auditable, version-controlled
- YAML configuration is easy to diff and review
- Configuration errors fail fast at bundle compilation
- Actions are thread-safe and reusable across requests

**Negative:**
- YAML is less discoverable than IDE hints in PHP
- Dynamic field names (e.g., environment variables in auth) require careful parsing

## References

- [`AbstractAction`](../../src/Core/Contract/Action/AbstractAction.php) — base class
- [`YamlConfigAdapter`](../../src/Infrastructure/Adapter/YamlConfigAdapter.php) — configuration parsing
- [`tests/Core/AbstractActionTest.php`](../../tests/Core/AbstractActionTest.php) — action immutability tests
