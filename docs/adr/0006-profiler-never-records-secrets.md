# 0006 · Profiler never records secrets

- **Status:** Accepted
- **Date:** 2026-05-18

## Context

In development and testing, the Symfony Profiler records integration calls for debugging:
- Method, status, URL, duration
- But **not** the request/response body or authorization headers

Developers use this to trace what APIs were called. However, if we record authorization headers or request bodies, we risk:
1. Logging tokens and API keys to the profiler (disk, inspectable in browser)
2. Exposing user input or sensitive data (query parameters, POST payloads)
3. Violating privacy regulations (GDPR, etc.)

## Decision

**Record only the bare minimum: method, path template, response status, timing.**

The `IntegrationCall` object (stored in the profiler) records:
- Action name
- HTTP method
- Path template (not resolved)
- HTTP status
- Duration
- Number of attempts (if retried)

It **explicitly does not** record:
- Authorization headers
- Request body
- Response body
- Context/path parameters

## Alternatives considered

1. **Record everything, hash sensitive fields**
   - Pros: complete audit trail
   - Cons: hashing is cryptographic overhead; still leaks field names and structure
   - Rejected: minimal recording is safer and simpler

2. **Redact secrets at display time** (Profiler only)
   - Pros: complete logging for emergency use
   - Cons: risk of displaying secrets if display code breaks; still in memory
   - Rejected: don't record if you don't need it

## Consequences

**Positive:**
- Profiler is safe for shared environments (CI, shared dev machines)
- No accidental secret leakage to browser tools
- Reduced profiler storage overhead

**Negative:**
- Debugging API issues requires looking at HTTP client logs or the integration YAML separately
- If a call fails mysteriously, the profiler won't show you why (body not logged)

## References

- [`IntegrationCall`](../../src/Infrastructure/Debug/IntegrationCall.php) — profiler data structure
- [`TracingMiddleware`](../../src/Infrastructure/Debug/TracingMiddleware.php) — profiler integration
- Profiler secret-security test to be added in v4.6 (aspirational ADR)
