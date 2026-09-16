# 0004 · Concurrency with lazy HTTP responses

- **Status:** Accepted
- **Date:** 2026-03-15

## Context

When fetching data from multiple external APIs in parallel (`sendMany()`), there are two models:
1. **Eager:** dispatch all requests, wait for all responses before returning (simpler but slower)
2. **Lazy:** dispatch all requests, return response objects immediately, consume on-demand (complex but faster)

The choice impacts:
- Time to first response
- Memory usage under high concurrency
- Error handling (do all items fail if one fails?)

## Decision

**Use Symfony HttpClient's lazy response model.**

`SymfonyHttpClientAdapter::sendMany()`:
1. Dispatches all `PreparedRequest`s concurrently
2. Returns response objects immediately (response body not yet consumed)
3. Body is fetched when first accessed (lazy)
4. Individual item failures don't abort the batch

This avoids blocking on the slowest request while still benefiting from parallelism.

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
- Better throughput: fast responses are available while slow ones are still loading
- Better memory: response bodies are streamed, not buffered entirely
- Matches Symfony HttpClient design philosophy

**Negative:**
- Errors only surface when response is accessed (lazy)
- Batch result iteration order doesn't guarantee response order
- Caller must handle per-item failures (more complexity)

## References

- [`SymfonyHttpClientAdapter::sendMany()`](../../src/Infrastructure/Http/SymfonyHttpClientAdapter.php#L100) — lazy batch implementation
- [`tests/Infrastructure/SymfonyHttpClientAdapterBatchTest.php`](../../tests/Infrastructure/SymfonyHttpClientAdapterBatchTest.php) — batch concurrency tests
- [Symfony HttpClient Docs](https://symfony.com/doc/current/http_client.html#streaming-responses) — lazy response design
