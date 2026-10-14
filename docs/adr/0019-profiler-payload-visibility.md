# 0019 · Opt-in payload visibility in the profiler

- **Status:** Accepted
- **Date:** 2026-10-14

## Context

[0006](0006-profiler-never-records-secrets.md) decided that the Symfony Profiler panel never records request/response bodies, resolved context values or credentials — only bounded metadata (action, method, raw path, duration, status, cached flag, exception class, and since [0018](0018-body-type-invariant.md) the body's class name). That default protects against secrets/PII reaching profiler storage, which persists to disk and is inspectable through the browser.

In practice, metadata alone is often not enough to debug an integration: knowing that `CreateEmployee` failed with a 422 doesn't say *which* field was wrong, or what the upstream API actually sent back. Developers end up adding temporary `dump()`/`var_dump()` calls or reaching for an external HTTP proxy (e.g. mitmproxy) to see what was actually sent and received — exactly the kind of friction the profiler panel exists to remove for every other piece of call metadata.

## Decision

**Add a per-integration opt-in, `profiler_payloads` (default `false`), that makes `TracingMiddleware` additionally record the action body, the resolved context and the raw response body — never headers, never credentials, never a failed call's response — on `IntegrationCall`.**

This does not change the default from 0006: with `profiler_payloads` unset (or `false`), behavior is byte-for-byte identical to before this ADR. When set to `true`:

- `IntegrationCall::bodyData` — `$action->getBody()?->toArray()`.
- `IntegrationCall::contextData` — `$context?->toArray()`.
- `IntegrationCall::responseData` — the raw response body (`$result['body']`), **only when the call succeeded**. A failed call's `responseData` stays `null`, same as 0006's existing rule that exception messages and upstream error bodies are never persisted — that rule is not relaxed by this flag.
- Authorization headers and other credentials are **never** captured, regardless of the flag. They were never part of what `TracingMiddleware` sees as a candidate to record in the first place.

The flag only has any effect while `TracingMiddleware` is wired at all, which — unchanged from 0006/[debugging.md](../advanced/debugging.md) — requires `kernel.debug === true` and an active profiler service. `profiler_payloads: true` left in a config block that also loads in production is inert there: the compiler pass never registers `TracingMiddleware` outside `kernel.debug`, so there is no code path where the flag's value is even read.

Config hygiene: setting it under Symfony's `when@dev:` block is recommended in the docs as explicit intent, but is not what makes this safe — the `kernel.debug` gate already does that, independent of where in the YAML the flag was declared. (This was evaluated directly: by the time compiled config reaches the bundle's `Configuration`/`IntegrationCompilerPass`, Symfony has already resolved and flattened `when@env:` blocks — the bundle has no way to know, or enforce, that a value came from one.)

## Alternatives considered

1. **Record everything unconditionally (remove the 0006 restriction)**
   - Pros: simplest; most information available by default
   - Cons: reintroduces exactly the secret/PII exposure 0006 was written to prevent; removes a safe default for every integration, not just the ones that need this
   - Rejected: the whole point of 0006 is a safe-by-default rule; this would delete it

2. **Redact known-sensitive field names (e.g. `password`, `token`) before storing**
   - Pros: looks like a middle ground — "mostly safe" content
   - Cons: this is 0006's own rejected alternative #1, for the same reason: an allowlist/denylist of field names can never be exhaustive, and a false sense of safety is worse than an explicit opt-in
   - Rejected: same reasoning as 0006, applied again here

3. **Tie the flag to `kernel.environment === 'dev'` instead of (or in addition to) `kernel.debug`**
   - Pros: slightly harder to leave on by accident in a prod-like environment that happens to run with debug on
   - Cons: inconsistent with every other debug-gated feature in this bundle (`TracingMiddleware` itself, the whole profiler collector), which all key off `kernel.debug` — the Symfony-idiomatic "is it safe to show more" signal; would also lock out legitimate non-`dev`-named environments (e.g. a `staging` environment that intentionally runs with debug on)
   - Rejected: `kernel.debug` is already the correct, existing boundary; duplicating it with a stricter, differently-named check would be inconsistent for no real gain — a prod misconfigured with `APP_DEBUG=1` already exposes far more (full profiler UI, stack traces, env vars) than this flag adds on top

4. **Capture response bodies even on failure**
   - Pros: would show the exact upstream error body, often the most useful thing when debugging a 4xx/5xx
   - Cons: this is precisely the data 0006 singled out as highest-risk — upstream error bodies are exactly where secrets/tokens/PII tend to leak into exception messages; extending capture to the error path would weaken, not extend, the existing exception-message rule
   - Rejected: keep the failure path exactly as 0006 left it; `responseData` is success-only

## Consequences

**Positive:**
- Debugging an integration failure (wrong field, wrong shape, unexpected upstream response) no longer requires temporary `dump()` calls or an external HTTP proxy.
- The default behavior for every existing integration is unchanged — this is purely additive and opt-in.
- The safety boundary (`kernel.debug`) is inherited from the existing `TracingMiddleware` wiring, not reinvented.

**Negative:**
- An integration that opts in and is somehow loaded in a `kernel.debug === true` production-like environment will have its outgoing bodies, contexts and response bodies written to local profiler storage. This is not a new risk introduced by this feature — `kernel.debug` in production is already a severe, independent misconfiguration (full profiler UI, stack traces, environment variables) — but it is a real incremental addition on top of that failure.
- `profiler_payloads` is read per integration at compile time; it cannot be toggled at runtime per request.

## References

- [`Configuration::getConfigTreeBuilder()`](../../src/Bundle/DependencyInjection/Configuration.php) — `profiler_payloads` config node
- [`IntegrationCompilerPass::buildMiddlewareClient()`](../../src/Bundle/DependencyInjection/Compiler/IntegrationCompilerPass.php) — threads the flag into `TracingMiddleware`, inside the existing `kernel.debug` gate
- [`TracingMiddleware`](../../src/Infrastructure/Debug/TracingMiddleware.php) — capture logic, success-only `responseData`
- [`IntegrationCall`](../../src/Infrastructure/Debug/IntegrationCall.php) — `bodyData`/`contextData`/`responseData` fields
- [`tests/Infrastructure/Debug/TracingMiddlewareTest.php`](../../tests/Infrastructure/Debug/TracingMiddlewareTest.php) — capture-on/off and failure-path regression tests
- [0006](0006-profiler-never-records-secrets.md) — the default this ADR extends, not replaces
- [`docs/advanced/debugging.md`](../advanced/debugging.md) — user-facing documentation
