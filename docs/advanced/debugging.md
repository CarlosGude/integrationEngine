# Debugging — Console and Symfony Profiler

## Inspect configured integrations

```bash
php bin/console debug:integration
php bin/console debug:integration my_api
php bin/console debug:integration my_api --format=json
```

The list shows integration names, client types (or custom service IDs) and YAML
config paths. Selecting an integration shows each action's name, configured HTTP
method, path and class. Missing methods and paths use the same `POST` and `/`
defaults as the YAML adapter. Webhooks are excluded from the action list.

Inspection reads configuration without instantiating action or body classes,
resolving path placeholders or sending HTTP requests. It omits base URLs,
authorization and headers. It is a configuration overview, not a connectivity
check or validation that the referenced classes exist.

`--format=json` formats successful results for scripts. Unknown integrations and
unreadable or invalid action files return exit code 1 with a text error;
unsupported output formats return exit code 2.

## Symfony Profiler

In `dev`/`test`, every outgoing call made through any configured integration is recorded
and shown in the Symfony Toolbar/Profiler — automatically, with zero configuration.

---

## What you see

A panel listing every call made during the current app request: integration name,
action, HTTP method, raw path template, duration, HTTP status when available, and the
exception class when a call fails. Exception messages, upstream response bodies,
resolved URLs and runtime context values are deliberately not stored.

The toolbar shows a compact summary — total calls, total time, cache hits and an error
badge when any call failed.

This is per **app request**, not per outgoing call. If a controller triggers three calls
across two integrations (including a `sendMany()` batch), all three show up in the same
panel. Batch entries are recorded in the input request order after the batch completes;
they are not ordered by network completion time.

---

## Why only `dev`/`test`

The panel works primarily through `TracingMiddleware`, the innermost built-in layer in
`MiddlewareClient`, which times requests that reach the transport and reports them to a
collector. Cache hits never reach `TracingMiddleware`; `CachingMiddleware` records those
directly with zero transport duration and marks them as cached. `IntegrationCompilerPass` only wires `TracingMiddleware` when **all three** hold:

1. `kernel.debug` is `true`.
2. `symfony/http-kernel`'s `DataCollectorInterface` is available — it is not a required
   dependency of this bundle, though in a real Symfony app it always is (pulled in by
   `symfony/framework-bundle`).
3. A `profiler` service is registered in the container — the signal that
   `symfony/web-profiler-bundle` is actually installed and active.

The third check matters on its own: a project can have `symfony/http-kernel` (almost
every Symfony app does) without ever installing `web-profiler-bundle` — e.g. a `prod`-like
`dev` setup, or an app that deliberately leaves the profiler out. Without it, nothing
would ever read the collected data, so the compiler pass skips the decoration rather than
paying for timing and accumulation that nobody will see.

In `prod`, or whenever any of the three checks fails, the engine uses the configured
client directly — exactly as if this feature did not exist. No timing, no accumulation,
no memory cost.

---

## Why middleware, not engine instrumentation

The panel records action-level transport metadata — HTTP method, raw path template,
duration and status when available — because `TracingMiddleware` sits at the HTTP adapter
boundary. It deliberately does **not** retain the fully resolved URL or runtime context
values. It also does **not** instrument inside `IntegrationEngine::send()`; the core engine
flow is untouched. The Action's logical name comes from `$action::getName()`, so the panel
can identify `GetEmployee` without persisting a resolved path such as a concrete employee
identifier.

This trade-off was deliberate: instrumenting inside `IntegrationEngine` would mean
touching the one class every request flows through, for a feature that is purely
observability. A middleware layer keeps the core untouched and the feature fully opt-in.

---

## Relationship with `LoggerInterface`

The bundle already logs specific events through the optional `LoggerInterface` passed to
`IntegrationEngine`/`DynamicAuthHandler` — auth token cache hits, 401 retries. That logger
is for **events worth a log line in any environment**, including production.

The profiler panel is the complementary view: outgoing calls for one application request,
normally in `dev`/`test`. It is a per-request debugging view, **not** an application
audit log or production observability store. Symfony may persist profiler data, which is
why the collector keeps only bounded metadata and never stores exception messages,
payloads, resolved URLs or credentials. Use application logging/metrics for persistent
operational evidence and the profiler for request-scoped diagnosis.
