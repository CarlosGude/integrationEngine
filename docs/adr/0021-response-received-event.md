# 0021 · `ResponseReceived` event before mapping

- **Status:** Accepted
- **Date:** 2026-10-22

## Context

`RequestSent` and `ResponseMapped` bracket the whole engine operation: `ResponseMapped::durationMs` includes connection resolution, token fetching, middleware, transport and mapping. With many integrations and endpoints, that tells you which action is slow but not whether the time goes to the upstream API or to the mapper.

v5.2 solved this with `HttpResponseReceived` ([0013](0013-intermediate-timing-events.md)). The v8 rework to scalar-only events replaced the event set and did not carry it over, and nothing recorded that as a decision. 0013 also excluded dynamic-auth requests, because the token fetch was interleaved with the protected call.

## Decision

**Emit a scalar-only `ResponseReceived` event from the engine once the client has returned a response, immediately before mapping.**

- Fields: `integrationName`, `action`, `durationMs` (since the operation started), `statusCode`, `timestamp`, `requestKey`.
- Emitted in `send()` on both the static and the dynamic-auth path. The dynamic-auth path already receives the final raw response in one place (the `buildResponse` closure), so the 0013 exclusion no longer applies.
- Emitted for every `sendMany()` item whose transport succeeded, keyed by `requestKey`.
- Only responses that reach the mapper count. A transport failure goes straight to `RequestFailed`; the 401 that triggers a token refresh is already visible as `TokenRefreshed` (`rejected_401`). `ResponseReceived` followed by `RequestFailed` means the API answered and mapping failed.
- Response-cache hits emit it too: their mapping runs, and their near-zero pre-mapping time is accurate.
- Mapping time is `ResponseMapped::timestamp − ResponseReceived::timestamp`. `ResponseMapped` is unchanged.

## Alternatives considered

1. **Per-request network time reported by the transport** (e.g. Symfony HttpClient's `total_time` as an optional key in the client response)
   - Pros: exact per-request network time, including concurrent batches
   - Cons: widens the client response contract, touches the three built-in adapters, must be kept out of the response cache, and needs a rule for transport-level retries
   - Rejected for now: the engine-level split answers "mapper or upstream?" without touching transport

2. **`mappingDurationMs` on `ResponseMapped`**
   - Pros: a metrics callback listening to one event gets the split without keeping state
   - Cons: widens an existing event contract; does not distinguish "API answered, mapping failed" from a transport failure
   - Rejected: the separate event carries both the timing and that distinction

3. **Restore v7's `HttpResponseReceived` as it was**
   - Rejected: it excluded dynamic auth, and the name does not follow the v8 `RequestSent`/`ResponseMapped` pair (cache hits are not HTTP responses)

## Consequences

**Positive:**
- Slow upstream and slow mapping can be told apart per action, for static and dynamic auth alike
- Mapping failures are distinguishable from transport failures by event sequence alone
- No change to transport, adapters, cache or existing events

**Negative:**
- In a batch the engine gets every item's result at once, so an item's `ResponseReceived::durationMs` measures until the batch returned, not that request's own network time. The per-item mapping time stays exact
- Getting the mapping time means correlating two events (by `requestKey` in batches, by order within `send()`)
- Code asserting an exact event sequence now sees `ResponseReceived` between `RequestSent` and `ResponseMapped`

## References

- [`ResponseReceived`](../../src/Core/Event/ResponseReceived.php)
- [`IntegrationEngine`](../../src/Core/IntegrationEngine.php) — emission in `send()` and `sendMany()`
- [`tests/Core/Lifecycle/LifecycleEventsTest.php`](../../tests/Core/Lifecycle/LifecycleEventsTest.php) — event order, failures, dynamic-auth retry, batches
- [`tests/Core/Lifecycle/LifecycleDurationsTest.php`](../../tests/Core/Lifecycle/LifecycleDurationsTest.php) — pre-mapping and mapping time
- [lifecycle.md](../lifecycle.md) — timing semantics
