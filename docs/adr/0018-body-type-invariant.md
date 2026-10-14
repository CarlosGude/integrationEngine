# 0018 · Body type invariant, validated at config time

- **Status:** Accepted
- **Date:** 2026-10-14

## Context

An action's YAML config may declare a `body` class (`body: App\...\ActionBody`). Before this decision, `YamlConfigAdapter::resolveBody()` only checked:

1. presence/absence — a body passed to an action that declares none is an error, and vice versa;
2. that the *declared* class implements `ActionBodyInterface`.

If the caller actually passed a body object, it was returned unchanged — `resolveBody()` never checked that it was an instance of the class declared in the YAML. A caller could pass any `ActionBodyInterface` and the mismatch would surface only indirectly: `toArray()` would serialize the wrong fields, and the external API would reject or misinterpret the request. For GraphQL/SOAP-style integrations, where the body *is* the entire request (query + variables, or envelope), this indirection is worse — a wrong body can still satisfy `ActionBodyInterface` and reach the transport layer before failing.

This mirrors the problem [0002](0002-mapper-invariant.md) solved for mappers: a declared contract (`mapper()` / YAML `body:`) that nothing enforced against what was actually supplied at the call site.

## Decision

**A body passed to `send()` / `sendMany()` must be an instance of the class declared in the action's YAML `body:` entry.**

`YamlConfigAdapter::resolveBody()` enforces this: if a body is provided and it is not `instanceof` the declared class, it throws `\InvalidArgumentException` naming the action, the declared class and the received class — before `AbstractAction::create()` is called, so before any HTTP call is made.

As a secondary, lower-risk piece of the same decision, `TracingMiddleware` now also records the action body's **class name** (never its contents) on every `IntegrationCall`, surfaced as a "Body" column in the Symfony Profiler panel. This is metadata, consistent with the recording rules in [0006](0006-profiler-never-records-secrets.md): it helps diagnose body-type mistakes from the profiler without ever persisting payload data.

## Alternatives considered

1. **Warn via a logger instead of throwing**
   - Pros: non-breaking; surfaces the mistake without changing behavior for existing (if accidentally mismatched) callers
   - Cons: inconsistent with the two sibling checks in the same method, which already throw for "missing body" and "class doesn't implement the interface"; requires injecting a `LoggerInterface` into a config adapter that otherwise has none; a silently-wrong body is exactly the kind of mistake that should fail loud and early
   - Rejected: consistency with the adapter's existing hard-fail convention, and fail-fast matters most for GraphQL/SOAP-style bodies where a bad request can still have side effects

2. **Check at `IntegrationEngine::send()` / dispatch time instead of in `YamlConfigAdapter`**
   - Pros: would apply uniformly to any `ConfigPort` implementation, not just the YAML one
   - Cons: `ConfigPort::getAction()` already receives the body and is the one place that knows both the declared class (from config) and the provided instance; duplicating that knowledge at the engine level would require `ConfigPort` to expose the declared body class separately
   - Rejected: the YAML adapter is the only shipped `ConfigPort` implementation and is where the declared type already lives; a custom `ConfigPort` can enforce its own equivalent check

3. **Dedicated exception class (e.g. `BodyActionMismatchException`), mirroring `MapperActionMismatchException`**
   - Pros: consistent naming with the mapper invariant; easier to catch specifically
   - Cons: the mapper invariant is checked from two call sites (`AbstractMapper::map()` and `ResponseBuilder::applyMapper()`), which justifies a shared, named exception; this check has exactly one call site, and its two sibling checks in `resolveBody()` already throw plain `\InvalidArgumentException`
   - Rejected: a single-use dedicated class would be an abstraction without a second caller; plain `\InvalidArgumentException` keeps the three `resolveBody()` checks consistent with each other

## Consequences

**Positive:**
- A body of the wrong type is rejected before the HTTP call, not after an upstream 400/422 — most valuable for GraphQL/SOAP actions where the body is the whole request.
- The error message names the action, the expected class and the received class, pointing directly at the mistake.
- The Profiler panel now shows which body class (if any) was sent per call, without recording payload data.

**Negative:**
- A caller that was previously passing a mismatched body and relying on the external API's own (looser or coincidentally-compatible) validation will now get a hard failure instead of whatever happened before. No public interface or method signature changes, so this ships as a minor release ([0007](0007-versioning-policy-after-v4.md)) — but it is a real behavior change for that specific, already-incorrect edge case.
- The invariant lives in `YamlConfigAdapter` specifically; a custom `ConfigPort` implementation does not get it for free.

## References

- [`YamlConfigAdapter::resolveBody()`](../../src/Infrastructure/Adapter/YamlConfigAdapter.php) — the check
- [`tests/Infrastructure/YamlConfigAdapterTest.php`](../../tests/Infrastructure/YamlConfigAdapterTest.php) — `getActionThrowsWhenProvidedBodyDoesNotMatchDeclaredClass`
- [`TracingMiddleware`](../../src/Infrastructure/Debug/TracingMiddleware.php), [`IntegrationCall`](../../src/Infrastructure/Debug/IntegrationCall.php) — profiler body-class visibility
- [0002](0002-mapper-invariant.md) — the analogous invariant for mappers
- [0006](0006-profiler-never-records-secrets.md) — profiler metadata-only recording rule
