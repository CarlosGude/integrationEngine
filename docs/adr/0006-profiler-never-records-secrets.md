# 0006 · Profiler never records secrets

- **Status:** Accepted
- **Date:** 2026-05-18

## Context

In development and testing, the Symfony Profiler records integration calls for debugging. Profiler data may be persisted to disk and inspected through the browser, so recording request bodies, response bodies, authorization headers or arbitrary exception messages would create an unnecessary secret/PII exposure path.

In particular, an upstream HTTP error may include its response body in an exception message. Display-time redaction would be too late because the sensitive value would already have entered profiler storage.

## Decision

**Record metadata, never payloads or exception messages.**

The profiler's `IntegrationCall` records:

- integration name and action name;
- HTTP method;
- raw action path template, not the resolved URL;
- duration;
- HTTP status when known;
- whether the result came from cache;
- the exception **class name** when a call fails.

It explicitly does **not** record:

- authorization headers or credentials;
- request or response bodies;
- resolved context/path values;
- exception messages or upstream error bodies.

`IntegrationEngineDataCollector::recordCall()` receives the original throwable only long enough to classify the failure by class; its message is discarded before the `IntegrationCall` is created.

## Alternatives considered

1. **Record everything and redact at display time**
   - Pros: richer debugging information
   - Cons: sensitive data still reaches profiler memory/storage and a rendering regression could expose it
   - Rejected: do not retain data the profiler does not need

2. **Special-case only HTTP response exceptions**
   - Pros: protects the most obvious upstream-body path
   - Cons: arbitrary exception messages can also contain tokens, URLs or application data
   - Rejected: the invariant is simpler and stronger when no exception message is stored

## Consequences

**Positive:**

- The profiler cannot leak an upstream response body through `IntegrationCall::error`.
- Error rows still identify the exception class and HTTP status.
- The rule is enforced by a regression test containing a deliberate secret value.

**Negative:**

- Detailed provider error text must be inspected through an explicitly configured application/HTTP-client logging strategy rather than the profiler.

## References

- [`IntegrationCall`](../../src/Infrastructure/Debug/IntegrationCall.php)
- [`IntegrationEngineDataCollector`](../../src/Infrastructure/Debug/IntegrationEngineDataCollector.php)
- [`TracingMiddleware`](../../src/Infrastructure/Debug/TracingMiddleware.php)
- [Profiler regression test](../../tests/Infrastructure/Debug/IntegrationEngineDataCollectorTest.php)
