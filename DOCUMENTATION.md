# IntegrationEngine — Documentation

## Mental model

An integration is a directory. An endpoint is a subdirectory. Each endpoint contains
exactly two things: a request side and a response side. Nothing else is allowed to spread.

The engine enforces this structure at the framework level — it is not a convention you
can drift from, it is the contract.

---

## Lifecycle of an integration

The recommended starting point is the scaffolding command — it generates the facade,
action map YAML, `Action`, `Mapper`, and `Response` with the correct structure and
namespaces, leaving only `transform()` and the DTO fields to fill in:

```bash
php bin/console make:integration MyApi GetEmployee
```

1. **Scaffold** — run `make:integration` to generate the skeleton
2. **Configure** — set `base_url` and `config_path` in `integration_engine.yaml`
3. **Implement** — fill in `transform()` in the mapper and the DTO fields in the response
4. **Use** — call the facade from an application service

---

## The engine pipeline

When you call `$engine->send(actionName, context, body, headers, baseUrl, connection)`:

1. **Config resolution** — reads the YAML, finds the action entry, resolves any
   `{placeholder}` the body supplies, instantiates the action class with method, path,
   body, and authorization.
2. **Connection resolution** — if `connection` was passed, resolves it to
   `ConnectionCredentials` and applies any `base_url`/authorization override.
3. **Authorization** — if dynamic auth, fetches and caches the token (namespaced per
   connection), then rebuilds the action with static auth.
4. **HTTP execution** — resolves any remaining path placeholders from context, builds
   headers, serializes the body, runs request middlewares (if configured), executes
   the request.
5. **Mapping** — validates `$mapper::getAction() === $action::class`, calls
   `transform()` with the response body and headers, returns a typed `ResponseInterface`.

---

## Actions

An action declares one endpoint: HTTP method, path, mapper. No logic, no state.

```php
final class GetEmployeeAction extends AbstractAction
{
    public static function getName(): string   { return 'GetEmployee'; }
    public static function hasResponse(): bool { return true; }
    public static function mapper(): ?string   { return GetEmployeeMapper::class; }
}
```

```yaml
GetEmployee:
    action: App\...\GetEmployeeAction
    method: GET
    path:   /employees/{id}
```

→ [Actions in depth](docs/actions.md) — all YAML options, `hasResponse: false`, the
stateless invariant.

---

## Context and path parameters

`{placeholder}` tokens in the path resolve from two sources, in priority order: the
action's **body** first (declare `body:` on the action — no extra class needed), then
**context** for whatever the body doesn't supply. For optional query params, implement
`PathResolvableContextInterface`.

```php
// From the body — no context needed:
$engine->send('UpdateEmployee', body: UpdateEmployeeBody::create(['id' => 42, 'name' => 'Ada']));

// From context — for values that aren't part of the body:
DefaultActionContext::create(['id' => 42]) // → /employees/42
```

→ [Context and path resolution](docs/context-and-path.md) — body-sourced placeholders,
required vs. optional params, custom context with validation, decision table.

---

## Mappers and responses

A mapper transforms the raw HTTP response array into a typed DTO. One mapper per action.

```php
final class GetEmployeeMapper extends AbstractMapper
{
    public static function getAction(): string { return GetEmployeeAction::class; }

    protected static function transform(AbstractAction $action, array $response, array $headers): ResponseInterface
    {
        return GetEmployeeResponse::create($response);
    }
}
```

```php
final readonly class GetEmployeeResponse implements ResponseInterface
{
    public function __construct(public int $id, public string $name) {}
    public static function create(array $data): self { ... }
    public function toArray(): array { ... }
}
```

→ [Mappers and responses](docs/mappers-and-responses.md) — type mapping table, nested
DTOs, shared mapper logic, the `toArray()` contract.

---

## Authorization

Declare auth in the YAML action entry. The engine handles header injection, token
fetching, caching, and 401 retries automatically.

```yaml
GetOrders:
    authorization:
        type:  bearer
        token: '%env(MY_API_TOKEN)%'
```

For OAuth 2.0 or session tokens, use `type: dynamic` — the engine calls the token action,
caches the result, and injects it transparently:

```yaml
GetOrders:
    authorization:
        type:        dynamic
        action:      FetchToken
        token_field: access_token
        ttl:         3600
```

→ [Authorization](docs/authorization.md) — all static types (bearer, basic, api\_key),
dynamic auth config, token action setup, caching (including per-connection isolation
for multi-connection integrations), 401 retry, Redis backend.

---

## Batch / Parallel Requests

Use `sendMany()` when you need N results before you can proceed. Returns a
`BatchResultCollection` — one `BatchResult` per key, independent successes and failures.

```php
$results = $engine->sendMany([
    'alice' => new EngineRequest(GetEmployeeAction::getName(), context: DefaultActionContext::create(['id' => 1])),
    'bob'   => new EngineRequest(GetEmployeeAction::getName(), context: DefaultActionContext::create(['id' => 2])),
]);

$results['alice']->isSuccess();  // bool
$results['alice']->response();   // ResponseInterface
$results['alice']->error();      // \Throwable|null
```

Real concurrency is independent of the protocol — it depends on whether the client
implements `BatchClientInterface`. The default REST client does.

→ [Batch / Parallel Requests](docs/batch-requests.md) — failure strategies,
`sendManyOrFail()`, concurrency per client type, `AbstractBatchMapper` for homogeneous
batches, mixed-action batches.

---

## HTTP clients

The default `rest` client handles standard REST APIs with no configuration. Set
`client: graphql` for GraphQL. For full control — retry logic, circuit breaking, custom
protocols — use `client_service:`. Every client returns `array{body, headers}` — the
decoded body plus the response's HTTP headers, propagated to the mapper.

```yaml
my_api:
    client_service: 'App\Infrastructure\Http\RetryingHttpClient'
```

→ [HTTP Clients](docs/clients.md) — GraphQL body interface, `client:` vs
`client_service:`, custom protocol adapters, `BatchClientInterface` for concurrency.

---

## Runtime connection resolution

For an integration that serves several connections at runtime (multi-tenant: one
store/account per customer) with different `base_url` and/or credentials, configure a
`connection_resolver` instead of building a separate `IntegrationEngine` per connection:

```yaml
my_api:
    connection_resolver: App\Infrastructure\Integrations\MyApi\MyApiConnectionResolver
```

```php
$engine->send('get_orders', connection: $tenantId);
```

→ [HTTP Clients — runtime connection resolution](docs/clients.md#runtime-connection-resolution--connectionresolverinterface) —
`ConnectionResolverInterface`, `ConnectionCredentials`, the dynamic-auth token cache
discriminator for connections sharing one `base_url`.

---

## Request middleware — full-request signing

For providers that sign the complete request (OAuth 1.0a, AWS SigV4) rather than a
static credential, implement `RequestMiddlewareInterface` — it runs on the
fully-resolved request immediately before the HTTP call:

```yaml
my_api:
    request_middlewares:
        - App\Infrastructure\Integrations\MyApi\OAuth1SigningMiddleware
```

→ [HTTP Clients — request middleware](docs/clients.md#request-middleware--full-request-signing) —
the `Request` value object, chain semantics, why `sendMany()` degrades to sequential
dispatch when configured.

---

## Debugging — Symfony Profiler

In `dev`/`test`, every outgoing call made through any configured integration shows up
in the Symfony Toolbar/Profiler automatically — no configuration needed. In `prod`, the
real client is used unwrapped: zero overhead.

→ [Debugging](docs/debugging.md) — what the panel shows, why it's a decorator and not
engine instrumentation, how it relates to the optional `LoggerInterface` logging.

---

## Anti-Corruption Layer

Integration DTOs must never reach the domain layer. The translation happens in an
application service:

```
Controller → ApplicationService → IntegrationFacade → Engine
                ↓
           DomainObject ← (translation happens here)
```

If the external API changes a field name or type, only the DTO, its mapper, and the
application service's translation code need to change. Domain objects and domain logic
are unaffected.

---

## Lifecycle Events & Observability (v5.2.0+)

Tap into integration lifecycle for logging, metrics, and observability with **zero boilerplate**.

### Quick Start

Generate observability setup:
```bash
php bin/console make:observability shopify
```

This creates `src/Integration/Shopify/ShopifyObservabilitySetup.php` with stubs for:
- **Logging** — automatic with async buffer (no perf overhead)
- **Metrics** — Prometheus histogram/counters
- **Error handling** — Sentry integration
- **Alerts** — Slack on slow requests

### Performance

Observability overhead with async logging: **+0.06ms per call** (unmeasurable).

Metrics only (no logs): **+0.01ms per call**.

Configure in `monolog.yaml` for production:
```yaml
monolog:
  handlers:
    main:
      type: buffer
      handler: stream
      buffer_size: 100  # Batch logs
      level: info       # Skip debug
```

### Manual Setup (Advanced)

```php
$dispatcher = new LifecycleEventDispatcher();

\IntegrationEngine\Infrastructure\Lifecycle\ObservabilitySetup::register(
    $dispatcher,
    $logger,
    [
        'logging' => true,
        'slow_request_threshold_ms' => 3000,
        'metrics_callback' => fn($e) => $prometheus->record($e),
        'error_callback' => fn($e) => Sentry\captureException($e->error()),
    ]
);

$engine = new IntegrationEngine(
    config: $config,
    client: $client,
    cache: $cache,
    integrationName: 'stripe',
    eventDispatcher: $dispatcher,
);
```

→ **[Observability Guide](./OBSERVABILITY.md)** — setup, performance options, real examples.
→ **[Lifecycle Events Guide](./LIFECYCLE.md)** — low-level event subscription.

---

## Inbound Webhooks (v5.1.0+)

IntegrationEngine also supports **receiving** webhooks from external platforms
(Shopify, WooCommerce, etc.). The webhook framework provides:

- **Multi-platform routing** — single endpoint handles multiple platforms
- **Signature verification** — HMAC-based validation, extensible per platform
- **Event mapping** — transform webhook payloads into typed DTOs
- **Idempotency** — automatic duplicate detection (24h fingerprint window)
- **Reliability** — dead-letter queue for failed webhooks, state machine for lifecycle tracking
- **Async processing** — Symfony Messenger integration for non-blocking handling

→ **[Inbound Webhooks Guide](./WEBHOOK.md)** — complete documentation for receiving and processing webhooks from external APIs.
