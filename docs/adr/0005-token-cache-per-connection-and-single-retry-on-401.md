# 0005 · Token cache per connection and single retry on 401

- **Status:** Accepted
- **Date:** 2026-06-28

## Context

Dynamic authentication (OAuth, JWT, API keys with expiry) requires:
1. Fetching a fresh token before calling the business API
2. Caching the token to avoid repeated requests
3. Handling token expiry (HTTP 401)

In multi-tenant scenarios, tokens are different per tenant (per connection). Also:
- A token might be cached but later rejected (expired in production)
- A fresh token might fail 401 (service-side issue, not worth retrying)

## Decision

**Cache tokens per connection; retry once with a fresh token on 401.**

1. **Per-connection caching:** Token cache key includes `connectionId` (or connection itself if no id provided)
2. **Single retry:** Only retry HTTP 401 if the token was from cache; if freshly fetched, fail immediately
3. **Batch consistency:** All items in a batch share the cached token (one fetch per batch, one retry per batch)

## Alternatives considered

1. **Global token cache** (one token for all connections)
   - Pros: simpler
   - Cons: breaks multi-tenancy; one tenant's expiry blocks another
   - Rejected: security and scalability issue

2. **Unlimited retries on 401**
   - Pros: handles temporary service glitches
   - Cons: risk of infinite loops; doesn't distinguish "token expired" from "access denied"
   - Rejected: one retry is safe; more suggests a broken configuration

3. **No retry; caller handles 401**
   - Pros: explicit control
   - Cons: every caller must implement the same retry logic; error-prone
   - Rejected: retry is automatic and safe; caller can opt-out by not using dynamic auth

## Consequences

**Positive:**
- Token expiry is transparently handled
- Multi-tenant scenarios work without extra code
- Batch efficiency: one token fetch, one retry for all items
- Single-threaded retry logic avoids race conditions

**Negative:**
- Requires understanding of "connection" concept
- Retry happens without caller's knowledge (implicit behavior)
- If token service is flaky, one error blocks the whole batch

## References

- [`DynamicAuthHandler`](../../src/Core/Auth/DynamicAuthHandler.php) — token fetch and cache logic
- [`BatchTokenRetry`](../../src/Core/Batch/BatchTokenRetry.php) — tracks pre-cached vs. fresh tokens
- [`tests/Core/DynamicAuthTest.php`](../../tests/Core/DynamicAuthTest.php) — 401 retry behavior
- [`tests/Core/DynamicAuthSadPathTest.php`](../../tests/Core/DynamicAuthSadPathTest.php) — token failure scenarios
- [`ConnectionResolverInterface`](../../src/Core/Contract/Connection/ConnectionResolverInterface.php) — multi-tenant support
