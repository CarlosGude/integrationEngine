# 0004 · Concurrency with lazy HTTP responses

- **Status:** Accepted
- **Date:** 2026-03-15

## Context

When fetching multiple external resources in `sendMany()`, the transport can either dispatch and consume each request sequentially or exploit Symfony HttpClient's lazy handles to dispatch several requests before consuming their responses.

The choice impacts:
- Time to first response
- Memory usage under high concurrency
- Error handling (do all items fail if one fails?)

## Decision

**Use Symfony HttpClient's lazy response model.**

`SymfonyHttpClientAdapter::sendMany()`:

1. builds and dispatches every request handle first;
2. then consumes each dispatched response into the bundle's normal array response shape;
3. captures failures per key instead of aborting unrelated items;
4. returns the completed keyed result array only after that consumption pass.

The public batch API is therefore **not lazy to its caller**. Concurrency comes from dispatch-all-then-consume-all using Symfony's lazy HTTP response handles internally.

## Alternatives considered

1. **Eager evaluation** (wait for all responses)
   - Pros: simpler, easier to reason about
   - Cons: batch speed = slowest request; wastes parallelism potential
   - Rejected: performance regression for use cases with N slow requests

2. **PHP fibers/threads**
   - Pros: true parallelism at OS level
   - Cons: requires PHP 8.1+, new async runtime overhead, still lazy at HTTP layer
   - Rejected: HTTP client already parallelizes at curl level; fibers add complexity without benefit

## Consequences

**Positive:**
- Better throughput than per-item send/consume sequencing
- Preserves one result/failure per caller key
- Uses Symfony HttpClient's lazy transport model without leaking transport response objects through the engine API

**Negative:**
- The adapter still waits for the batch consumption pass before returning
- Request middleware forces a sequential fallback because it may inspect or replace each completed response
- Caller must handle per-item failures unless using the strict `sendManyOrFail()` wrapper

## References

- [`SymfonyHttpClientAdapter::sendMany()`](../../src/Infrastructure/Http/SymfonyHttpClientAdapter.php#L100) — lazy batch implementation
- [`tests/Infrastructure/SymfonyHttpClientAdapterBatchTest.php`](../../tests/Infrastructure/SymfonyHttpClientAdapterBatchTest.php) — batch concurrency tests
- [Symfony HttpClient Docs](https://symfony.com/doc/current/http_client.html#streaming-responses) — lazy response design
