# Proposed scope for the next features

Status: design proposal, 2026-09-22. None of the options or metrics below is
implemented. These proposals are separate from the current release preparation.

## 1. Per-integration HTTP transport selection

The compiler currently injects `http_client` into each built-in adapter. Applications
can replace an entire adapter with `client_service`, but cannot select a different
Symfony HTTP transport per integration while retaining built-in adapter wiring.

Proposed first increment: add an optional `http_client_service` setting whose
service implements Symfony's `HttpClientInterface`. It supplies the transport to
REST, GraphQL and form-encoded adapters; omission retains `http_client`. Reject
configuring both `client_service` and `http_client_service` to avoid a silent override.
Document the existing constructor convention for other tagged adapters.

Applications could select a transport decorated with Symfony's
`NoPrivateNetworkHttpClient`. Symfony documents this decorator for blocking requests
to private networks; it is preferable to maintaining a second IP filtering
implementation. See [Symfony 6.4 HTTP client SSRF handling](https://symfony.com/doc/6.4/http_client.html#ssrf-server-side-request-forgery-handling).

Acceptance criteria:

- Existing configurations and intentionally internal API integrations are unchanged.
- The selected transport is used by all three built-in adapters, dynamic token
  requests, runtime base URL overrides and batches; middleware order is unchanged.
  Request middlewares remain supported by REST/GraphQL only; form-encoded clients
  retain their existing client-middleware support.
- Missing services and incompatible transport types produce useful configuration
  errors, with compiled-container tests on supported Symfony versions.
- Security tests exercise direct private addresses, IPv4/IPv6, public-to-private
  redirects and hostname resolution using controlled transports/resolution fixtures.
  Confirm the behavior of each supported Symfony release instead of inferring it
  from the currently installed version.
- The documented setup includes `symfony/http-foundation`, required by the decorator,
  without adding it as an unconditional bundle dependency.

This is an opt-in transport configuration, not a promise that every integration is
SSRF-safe. Custom adapters, proxies and allowed internal destinations remain explicit
application choices. Do not expose a superficial hostname check in a request
middleware: redirects and resolved network addresses also matter.

Compatibility constraint: the decorator's `allowList` constructor argument was
introduced in Symfony 8.1, so it cannot be used unconditionally in the supported
6.4/7.x/8.0 matrix. See the version note in the
[current Symfony documentation](https://symfony.com/doc/current/http_client.html#ssrf-server-side-request-forgery-handling).

## 2. Optional PHPStan mapper-pairing rule

The bundle already enforces mapper/action pairing at runtime, and PHPStan max
checks PHP types. The missing check is whether two statically declared class
references agree: an action returns `OrdersMapper::class`, while that mapper
declares a different action in `getAction()`.

Proposed first increment: an optional development extension for PHPStan 2 that
reports this mismatch when both return values are statically known. Use PHPStan's
AST/type APIs, with an error identifier such as `integrationEngine.mapperMismatch`.
Do not execute application methods during analysis. Dynamic declarations continue
to rely on the existing runtime invariant.

Acceptance criteria:

- Fixtures cover matching and mismatching pairs, namespace aliases, abstract and
  inherited declarations, null mappers for response-less actions, and dynamic
  declarations that cannot be proved wrong.
- Errors include the affected action and mapper and an accurate source line.
- A consuming app enables the extension explicitly; PHPStan remains a development
  dependency. Extension code is outside the bundle's broad Symfony service discovery,
  so production installation without PHPStan still boots.
- Use a separate optional package or a dedicated development autoload namespace;
  choose the distribution mechanism before implementation. No registry of plugins
  is required for this single development tool.

PHPStan provides custom rules and `RuleTestCase` for these checks; see its
[custom rule documentation](https://phpstan.org/developing-extensions/rules).
Do not introduce a blanket ban on arrays: raw response bodies and batch input arrays
are deliberate parts of this bundle's contracts.

## 3. Metrics contract before a Prometheus exporter

`ObservabilitySetup` already accepts metrics callbacks for `ActionCompleted` and
`ActionFailed`. These describe logical `send()` calls, including mapping. The current
`sendMany()` implementation does not emit the same lifecycle events per item.
Even `send()` resolves action configuration, connection and client before its
event-producing try/catch, so failures during that preparation currently emit no
completion/failure event.
Listener failures also need a policy: a listener throwing after another listener
has counted `ActionCompleted` can cause `send()` to emit `ActionFailed` as well.
Profiler batch timings divide elapsed batch time among items and are not individual
HTTP latencies. An exporter must not silently combine these meanings.

Proposed first increment: define and test logical-operation metrics independently
of a particular Prometheus PHP client:

- `integration_engine_operations_total` counter, labels `integration`, `action`
  and `outcome` (`success` or `failure`).
- `integration_engine_operation_duration_seconds` histogram with the same bounded
  labels; convert the current event durations from milliseconds.
- Count a completed `send()` once, including its mapping outcome. Token acquisition
  and the cached-token 401 retry do not become extra logical operations.

Before advertising complete operation coverage, define completion/failure events
for single-call preparation as well as per-item batch events and timing semantics
for preparation failures, mapping failures, cache hits and retries.
Either deliver that contract first or explicitly ship single-call metrics only.

Acceptance criteria for the later exporter:

- The consuming application owns registry storage across PHP workers and the
  scrape endpoint. Choose the optional client library after confirming that model.
- Lifecycle listeners are actually instantiated once and use the engine's dispatcher.
- No metric labels contain tenant IDs, resolved URLs, tokens, request payloads or
  exception messages. Keep configured action and integration names bounded.
- Tests assert counts and millisecond-to-second conversion for success, failure,
  cached responses and auth retry; the documented batch policy is also tested.
- Define and test how observer failures affect outcomes before claiming exactly-once
  counts, including an exception from a listener following the metrics callback.
- Empty registries expose a valid response, and two workers do not silently lose
  each other's observations through process-local storage.

Base units and bounded labels follow Prometheus's
[metric naming](https://prometheus.io/docs/practices/naming/) and
[instrumentation guidance](https://prometheus.io/docs/practices/instrumentation/).

## Suggested order

Start with transport selection, which enables an existing Symfony security feature
without changing defaults. Mapper analysis can proceed independently. Metrics needs
its event/counting contract settled before selecting an exporter or storage adapter.
