# IntegrationEngine · Observability

The primary observability contract is the scalar lifecycle event set documented in [lifecycle.md](./lifecycle.md). In a normal Symfony application, listen to those events through Symfony's `event_dispatcher`.

This page documents the optional `ObservabilitySetup` helper and the generator built around it.

## Recommended Symfony setup

For application-wide production observability, ordinary Symfony listeners on the lifecycle events are the least surprising path, because bundle-managed engines already dispatch there. See [Lifecycle events — Symfony applications](./lifecycle.md#symfony-applications) for the listener shape, and [Metric design](#metric-design) below for what to record.

## `ObservabilitySetup` helper

`IntegrationEngine\Infrastructure\Lifecycle\ObservabilitySetup::register()` works with `LifecycleEventDispatcher` and can register:

- normal success/start/failure logging;
- a slow-request warning threshold;
- a metrics callback for `ResponseMapped|RequestFailed`;
- an error callback for `RequestFailed`;
- an optional integration-name filter.

```php
use IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher;
use IntegrationEngine\Infrastructure\Lifecycle\ObservabilitySetup;

ObservabilitySetup::register($dispatcher, $logger, [
    'logging' => true,
    'log_level' => 'info',
    'slow_request_threshold_ms' => 3000,
    'metrics_callback' => static function ($event): void {
        // application-owned metrics backend
    },
    'integration_filter' => 'shopify',
]);
```

The helper only receives events dispatched through that `LifecycleEventDispatcher` instance.

## Important v8 wiring caveat

The Symfony compiler pass currently injects Symfony's `event_dispatcher` directly into each bundle-managed `IntegrationEngine`. It does **not** replace that dispatcher with the `LifecycleEventDispatcher` service.

Therefore a service that merely calls `ObservabilitySetup::register()` on `LifecycleEventDispatcher` will not automatically observe the default bundle event stream. Either:

- prefer ordinary Symfony event listeners (recommended); or
- explicitly construct/wire the engine with the same `LifecycleEventDispatcher`/`SymfonyEventDispatcherAdapter` instance used by the helper.

This also means `make:observability` should be treated as **boilerplate generation**, not as proof that observers are active in a default bundle setup. Verify the dispatcher wiring in the consuming application.

## Generator

```bash
php bin/console make:observability my_api
# alias: make:obs
```

The command generates an application class under `src/Integration/<Name>/` and adds a service entry that calls its `register()` method. Customize the generated callbacks for the actual metrics/logging backend and apply the wiring caveat above.

## Metric design

Durations are logical-operation durations, not pure network timings, and `ResponseReceived` lets you separate mapping time from everything before it. See [Timing semantics](./lifecycle.md#timing-semantics) for what each duration includes and how it behaves in batches.

Good metric labels are bounded values such as integration name, action, outcome and status class. Avoid connection IDs, resolved URLs, payload fields, exception messages and any credential-derived value.

The bundle does not ship a Prometheus registry/exporter. Storage, aggregation and scrape endpoints belong to the consuming application.

For per-request inspection in development, use the Symfony profiler integration documented in [advanced/debugging.md](./advanced/debugging.md).
