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
resolved URLs and runtime context values are deliberately not stored by default.

The toolbar shows a compact summary — total calls, total time, cache hits and an error
badge when any call failed.

### Opt-in: showing body, context and response contents

By default the panel only shows metadata — never the actual body, context or response
content, per [ADR 0006](../adr/0006-profiler-never-records-secrets.md). Set
`profiler_payloads: true` on an integration to also show, per call:

- the action body's contents (`toArray()`);
- the resolved context's contents (`toArray()`);
- the response body — **only for successful calls**; a failed call's response/error body
  is still never recorded, same as the exception-message rule above.

Authorization headers and other credentials are never recorded, with or without this flag.

```yaml
# config/packages/integration_engine.yaml
when@dev:
    integration_engine:
        integrations:
            my_api:
                profiler_payloads: true
```

This flag only has any effect while `kernel.debug` is `true` — the same condition that
gates the whole panel (see "Why only dev/test" below). Declaring it under `when@dev:` is
recommended as config hygiene, making the intent explicit in the YAML, but it is not what
makes this safe: the `kernel.debug` gate already makes the flag inert in production
regardless of where it's declared. See [ADR 0019](../adr/0019-profiler-payload-visibility.md)
for the full rationale, including why this doesn't weaken ADR 0006's existing rules for
credentials and failed-call bodies.

#### Why this matters beyond the browser toolbar

The toolbar itself only renders when a browser loads the HTML response. Two common
workflows don't get that for free, and this is where `profiler_payloads` earns its keep
over a throwaway `dump()`:

- **Calling your own endpoint through Postman/Bruno/curl instead of a browser.** Symfony
  still profiles the request and returns an `X-Debug-Token` response header even though
  there's no toolbar to show it. Copy that token and open
  `/_profiler/{token}` in a browser afterwards to get the full IntegrationEngine panel for
  that exact request — including, with the flag on, what was actually sent to the upstream
  API and what came back. A black-box look at your own endpoint's response never shows
  that intermediate call; the profiler does.
- **Step debugging isn't always available.** Classic Xdebug remote debugging assumes a
  request that starts and ends one process. Worker-mode runtimes (FrankenPHP in worker
  mode, Swoole, RoadRunner) keep processes alive across requests, which makes attaching a
  step debugger noticeably more fiddly than on traditional PHP-FPM. The profiler panel
  needs no IDE↔process connection at all, so it keeps working exactly the same regardless
  of which runtime is serving the app.

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
boundary. It never retains the fully resolved URL (e.g. a concrete employee identifier
substituted into the path), with or without `profiler_payloads`. Runtime context values
are likewise not retained by default — only when `profiler_payloads` is explicitly enabled
for that integration, see "Opt-in" above. It also does **not** instrument inside
`IntegrationEngine::send()`; the core engine flow is untouched. The Action's logical name
comes from `$action::getName()`, so the panel can identify `GetEmployee` without the flag
needing to be on just to know which action ran.

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
why the collector keeps only bounded metadata by default and never stores exception
messages, resolved URLs or credentials — payloads are the one opt-in exception, see
"Opt-in" above. Use application logging/metrics for persistent operational evidence and
the profiler for request-scoped diagnosis.
