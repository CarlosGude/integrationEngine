# IntegrationEngine · Lifecycle Events

IntegrationEngine emits immutable scalar metadata for outbound calls, auth refreshes and inbound webhooks. Events never carry request bodies, response objects, tokens or exception objects.

## Event catalogue

| Event | Emitted when | Public fields |
|---|---|---|
| `RequestSent` | an outbound action has been resolved and is about to be dispatched | `integrationName`, `action`, `method`, `path`, `timestamp`, `connectionId`, `requestKey` |
| `ResponseMapped` | the response has been successfully mapped (or `EmptyResponse` built) | `integrationName`, `action`, `durationMs`, `statusCode`, `responseClass`, `timestamp`, `requestKey` |
| `RequestFailed` | preparation, transport, auth or mapping fails inside the engine flow | `integrationName`, `action`, `durationMs`, `statusCode`, `exceptionClass`, sanitized `message`, `timestamp`, `requestKey` |
| `TokenRefreshed` | dynamic auth resolves a new token | `integrationName`, token `action`, `reason`, `timestamp`, `requestKey` |
| `WebhookReceived` | a signed webhook is accepted and mapped | `integrationName`, `eventType`, `eventId`, `timestamp` |
| `WebhookRejected` | webhook validation/signature/type validation rejects the request | `integrationName`, rejection `reason`, `timestamp` |

`requestKey` is populated for `sendMany()` items so one listener can correlate per-item batch events without receiving the request itself.

`RequestFailed::message` is intentionally coarse (`Upstream request failed.` or `Integration request failed.`); detailed exception messages are not copied into the lifecycle event.

## Symfony applications

Bundle-managed engines receive Symfony's `event_dispatcher` when that service is available. The normal Symfony integration is therefore a listener/subscriber on the event class:

```php
use IntegrationEngine\Core\Event\ResponseMapped;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class IntegrationMetricsListener
{
    public function __invoke(ResponseMapped $event): void
    {
        // $event->integrationName
        // $event->action
        // $event->durationMs
        // $event->statusCode
    }
}
```

The same applies to `RequestSent`, `RequestFailed`, `TokenRefreshed`, `WebhookReceived` and `WebhookRejected`.

## Direct PSR-14 dispatcher

For framework-independent/manual wiring, `LifecycleEventDispatcher` implements `Psr\EventDispatcher\EventDispatcherInterface` and adds a small `subscribe()` API:

```php
use IntegrationEngine\Core\Event\RequestFailed;
use IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher;

$dispatcher = new LifecycleEventDispatcher();
$dispatcher->subscribe(RequestFailed::class, static function (RequestFailed $event): void {
    // record an application-specific metric or log entry
});
```

Pass the **same dispatcher instance** to `IntegrationEngine` if you construct the engine manually.

`SymfonyEventDispatcherAdapter` extends this local dispatcher and forwards dispatched events to a Symfony dispatcher as well. Use it only when you specifically need both `subscribe()` and Symfony listeners on the same event stream.

## Timing semantics

`durationMs` is measured around the logical engine operation. It can include configuration/connection resolution after the initial timestamp, authentication work, transport, retries, middleware and mapping. It is not a pure network-latency metric.

For batches, each item gets its own start timestamp and completion/failure duration. Concurrent requests can therefore have overlapping durations; summing them is not equivalent to wall-clock batch duration.

`RequestSent::path` is the action's raw path at that point. Body-backed placeholders may already have been consumed by `YamlConfigAdapter`; placeholders left for `ActionContextInterface` can still appear in that string. Treat it as operation metadata, not as a complete URL or trace span.

## Token refresh reasons

Dynamic authentication currently emits:

- `cache_miss` — no usable cached token existed;
- `rejected_401` — a cached token was rejected and the engine fetched one fresh token for the single retry.

A freshly fetched token that is rejected with 401 is not retried again.

## Failure semantics

Lifecycle listeners execute through the configured dispatcher. If a listener throws, normal dispatcher exception semantics apply; the engine does not promise observer isolation. Production metrics/log listeners should therefore avoid throwing into the integration flow.

For higher-level helper registration, see [OBSERVABILITY.md](./OBSERVABILITY.md). For profiler/request debugging, see [advanced/debugging.md](./advanced/debugging.md).
