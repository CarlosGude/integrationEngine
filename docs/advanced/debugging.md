# Debugging — Symfony Profiler

In `dev`/`test`, every outgoing call made through any configured integration is recorded
and shown in the Symfony Toolbar/Profiler — automatically, with zero configuration.

---

## What you see

A panel listing every call made during the current app request: integration name,
action, HTTP method, path, duration, and status (or the error, if it failed). The
toolbar shows a compact summary — total calls, total time, and an error badge when any
call failed.

This is per **app request**, not per outgoing call: if a controller triggers three
calls across two integrations (including a `sendMany()` batch), all three show up in the
same panel, in the order they completed.

---

## Why only `dev`/`test`

The panel works through `TracingMiddleware`, the innermost built-in layer in
`MiddlewareClient`, which times every `send()`/`sendMany()` call and reports it to a
collector. `IntegrationCompilerPass` only wires `TracingMiddleware` when **all three** hold:

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

The panel sees the request actually sent over the wire — HTTP method, path, duration,
status — because `TracingMiddleware` sits at the HTTP adapter boundary. It does **not**
instrument inside `IntegrationEngine::send()` — the engine flow is not touched. It
recovers the Action's logical name via `$action::getName()`, the same `AbstractAction`
instance the middleware already receives, so the panel reads e.g. `GetEmployee`, not just
`GET /api/v1/employee/42`.

This trade-off was deliberate: instrumenting inside `IntegrationEngine` would mean
touching the one class every request flows through, for a feature that is purely
observability. A middleware layer keeps the core untouched and the feature fully opt-in.

---

## Relationship with `LoggerInterface`

The bundle already logs specific events through the optional `LoggerInterface` passed to
`IntegrationEngine`/`DynamicAuthHandler` — auth token cache hits, 401 retries. That logger
is for **events worth a log line in any environment**, including production.

The profiler panel is the complementary view: **every** outgoing call of the current
request, only in `dev`/`test`, for the moment you're looking at a screen — not a
persistent record. Use the logger to know something happened; use the profiler to see
everything that happened on this one request.
