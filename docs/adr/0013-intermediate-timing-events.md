# 0013 · Intermediate Timing Events (HttpResponseReceived, ResponseMapped)

- **Status:** Superseded in v8
- **Date:** 2026-09-18

## Context

In v5.2.0, lifecycle events enable observability at every stage of request processing. However, the initial event set (ActionStarted, ActionCompleted, ActionFailed) groups the full duration without visibility into where time is actually spent.

Applications need to distinguish between:
1. **External API latency** — time spent waiting for the external API to respond
2. **Local transformation time** — time spent mapping the raw response to a typed DTO
3. **Framework overhead** — time spent in auth resolution, middleware, config loading

Without this breakdown, a slow request could be blamed on the integration framework when the real problem is an unresponsive external API—or vice versa.

## Decision

Add two intermediate lifecycle events fired only for direct HTTP calls (non-dynamic-auth path):

### `HttpResponseReceived`
Fired after the raw HTTP response arrives, before DTO mapping begins.

**Data:**
- `statusCode()` — HTTP status code
- `durationMs()` — time elapsed since ActionStarted (includes network + API processing)

**Use:** Track external API latency in isolation.

### `ResponseMapped`
Fired after the response is mapped to a DTO, before ActionCompleted.

**Data:**
- `httpDurationMs()` — time spent in HTTP call (same as HttpResponseReceived)
- `mappingDurationMs()` — time spent transforming raw response to DTO
- `totalDurationMs()` — time elapsed since ActionStarted

**Use:** See the complete breakdown: HTTP latency + mapping time + overhead.

## Alternatives considered

1. **No intermediate events** — Add timing breakdown to ActionCompleted only
   - **Rejected:** Observers already subscribed to ActionCompleted would get the data but couldn't react to HTTP failures before mapping begins. Also pollutes the event contract.

2. **Single "HttpFinished" event with all three durations**
   - **Rejected:** Ties timing data to a single event. Observers wanting to react immediately after HTTP (e.g., log slow APIs) can't do so.

3. **Extend existing ActionStarted to carry timing metadata**
   - **Rejected:** ActionStarted fires before timing is relevant (t=0). Semantically wrong.

4. **Fire intermediate events for all paths (including dynamic-auth)**
   - **Rejected:** Dynamic-auth paths have token fetch overhead interleaved. Breaking that down would require fine-grained timing inside AuthenticationHandler. Added complexity for edge case (most integrations use static auth).

## Consequences

### Positive

✅ **Precise bottleneck identification** — Separate gauges for `http_ms`, `mapping_ms`, `overhead_ms` let operators quickly identify whether slowness is external (API) or internal (framework/transformation).

✅ **Backward compatible** — New events are opt-in. Existing code observing only ActionCompleted continues to work unchanged.

✅ **Minimal overhead** — Timer calls are negligible (~0.01ms per event).

✅ **Direct HTTP path only** — Keeps auth path predictable (no interleaved token fetch timing). Simpler mental model.

### Negative

⚠️ **Separate event types** — Observers need to subscribe to multiple events if they want the full picture. More boilerplate than a single unified event (mitigated by ObservabilitySetup helper).

⚠️ **Not available for dynamic-auth** — Requests using dynamic authentication don't fire HttpResponseReceived/ResponseMapped. Documented, but could surprise first-time users. Mitigated: most integrations use static auth.

## References

- **Code:** `src/Core/Lifecycle/HttpResponseReceived.php`, `src/Core/Lifecycle/ResponseMapped.php`
- **Integration:** `src/Core/IntegrationEngine.php:send()` (lines 107-143) fires both events after measuring each phase
- **Documentation:** `OBSERVABILITY.md` "Detailed Timing" section with examples and use cases
- **Tests:** Lifecycle event tests verify event dispatch and data integrity (524 core tests passing)
