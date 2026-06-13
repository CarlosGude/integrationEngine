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

When you call `$engine->send(actionName, context, body, headers)`:

1. **Config resolution** — reads the YAML, finds the action entry, instantiates the
   action class with method, path, body, and authorization.
2. **Authorization** — if dynamic auth, fetches and caches the token, then rebuilds the
   action with static auth.
3. **HTTP execution** — resolves the path from context, builds headers, serializes the
   body, executes the request.
4. **Mapping** — validates `$mapper::getAction() === $action::class`, calls
   `transform()`, returns a typed `ResponseInterface`.

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

`DefaultActionContext` resolves `{placeholder}` tokens in the path. For optional query
params, implement `PathResolvableContextInterface`.

```php
DefaultActionContext::create(['id' => 42]) // → /employees/42
```

→ [Context and path resolution](docs/context-and-path.md) — required vs. optional
params, custom context with validation, decision table.

---

## Mappers and responses

A mapper transforms the raw HTTP response array into a typed DTO. One mapper per action.

```php
final class GetEmployeeMapper extends AbstractMapper
{
    public static function getAction(): string { return GetEmployeeAction::class; }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
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
dynamic auth config, token action setup, caching, 401 retry, Redis backend.

---

## Batch / Parallel Requests

Use `sendMany()` when you need N results before you can proceed. Returns a
`BatchResultCollection` — one `BatchResult` per key, independent successes and failures.

```php
$results = $engine->sendMany([
    'alice' => EngineRequest::create(GetEmployeeAction::getName(), DefaultActionContext::create(['id' => 1])),
    'bob'   => EngineRequest::create(GetEmployeeAction::getName(), DefaultActionContext::create(['id' => 2])),
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
protocols — use `client_service:`.

```yaml
my_api:
    client_service: 'App\Infrastructure\Http\RetryingHttpClient'
```

→ [HTTP Clients](docs/clients.md) — GraphQL body interface, `client:` vs
`client_service:`, custom protocol adapters, `BatchClientInterface` for concurrency.

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
