# 0001 · Stateless actions and declarative YAML configuration

- **Status:** Accepted
- **Date:** 2026-03-15

## Context

When integrating external APIs, the contract between the bundle and the application service involves:
1. **What goes in** — method, path parameters, request body, headers
2. **What comes out** — typed DTO

Rather than encoding this contract in PHP classes with mutable state (setters, state validation at call time), we chose to declare it once, make it immutable, and delegate I/O concerns to the engine.

## Decision

Each external operation is represented by an **immutable `AbstractAction` subclass plus YAML configuration**:

- the concrete action declares its stable name, whether it has a response, and its mapper;
- YAML supplies method, path, optional body class, authorization, cache TTL and timeout;
- runtime context/body/header values stay outside the action instance;
- `YamlConfigAdapter` parses the integration file when the adapter is constructed and creates immutable action values per dispatch.

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
- Structural bundle configuration is validated during container wiring; integration-YAML validation occurs when `YamlConfigAdapter` loads the file
- Action definitions are stateless and each resolved action value is immutable for its dispatch

**Negative:**
- YAML is less discoverable than IDE hints in PHP
- Dynamic field names (e.g., environment variables in auth) require careful parsing

## References

- [`AbstractAction`](../../src/Core/Contract/Action/AbstractAction.php) — base class
- [`YamlConfigAdapter`](../../src/Infrastructure/Adapter/YamlConfigAdapter.php) — configuration parsing
- [`tests/Core/AbstractActionTest.php`](../../tests/Core/AbstractActionTest.php) — action immutability tests
