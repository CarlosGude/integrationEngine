# IntegrationEngine — Documentation

## Mental model

An integration is a directory. An endpoint is a subdirectory. Each endpoint contains
exactly two things: a request side and a response side. Nothing else is allowed to spread.

The engine enforces this structure at the framework level — it is not a convention you
can drift from, it is the contract.

---

## Lifecycle of an integration

1. Define the integration (facade class + `NAME` constant)
2. Configure it (`integration_engine.yaml` + action map YAML)
3. Implement each action (Action + Mapper + Response + DTO)
4. Use it via the facade from an application service

---

## The engine pipeline

When you call `$engine->send(actionName, context, body, headers)`:

1. **Config resolution.** `ConfigPort::getAction()` reads the YAML, finds the action entry
   by name, instantiates the action class with method, path, body, and authorization.

2. **Authorization.** If the action carries a `DynamicAuthorizationConfig`, the engine
   fetches the token (from cache or by calling the auth action), then reconstructs the
   action with a `StaticAuthorizationConfig` in place of the dynamic one.

3. **HTTP execution.** `ClientInterface::send()` resolves the path from context, builds
   headers (default + auth + per-request), serializes the body, and executes the request.

4. **Mapping.** The engine validates that `$mapper::getAction() === $action::class`, then
   calls `$mapper::transform()` to produce a typed `ResponseInterface`.

If `hasResponse()` returns `false`, steps 3 and 4 still execute but step 4 returns
`EmptyResponse` without invoking the mapper.

---

## Actions

Actions are **stateless and immutable**. All properties are `readonly`. The engine
creates them via `Action::create()` — you never instantiate an action directly.

```php
final class GetEmployeeAction extends AbstractAction
{
    public static function getName(): string   { return 'GetEmployee'; }
    public static function hasResponse(): bool { return true; }
    public static function mapper(): ?string   { return GetEmployeeMapper::class; }
}
```

Runtime values (path params, filters, correlation IDs) never go in the action constructor.
They travel via `ActionContextInterface`, `ActionBodyInterface`, or `RequestHeadersInterface`.

---

## Context

`ActionContextInterface::toArray()` returns a key-value map that the engine uses to
resolve `{param}` placeholders in the path.

**`DefaultActionContext`** is a transparent wrapper — use it for the vast majority of cases:

```php
DefaultActionContext::create(['id' => 42, 'page' => 2])
```

**Custom context** makes sense when you need validation at construction time, or when
you want to accept domain objects directly instead of raw arrays:

```php
final readonly class GetEmployeeContext implements ActionContextInterface
{
    private function __construct(private int $id) {}

    public static function create(array $data): self
    {
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            throw new \InvalidArgumentException('Employee id must be a positive integer.');
        }
        return new self(id: $id);
    }

    public function toArray(): array { return ['id' => $this->id]; }
}
```

If you find yourself validating or casting values before passing them to
`DefaultActionContext::create()`, put that logic in a custom context class instead.

---

## Path resolution

The engine resolves the path in `AbstractAction::getPath()` using one of three strategies:

### 1. Default resolver — `{placeholder}` in YAML

`defaultResolvePath` applies the regex `/\{(\w+)\}/` to the full path string, including
any query string portion. Every placeholder must be present in the context or it throws
`PathResolutionException::missingParameter`.

```yaml
GetEmployee:
    path: /employees/{id}          # always required

FilterByDepartment:
    path: /employees?dept={dept}   # only use when param is always present
```

### 2. `resolvePathCallback` — optional or computed params

Override this method when any parameter is optional. You build the path string entirely
in the callback:

```php
protected function resolvePathCallback(): ?callable
{
    return static function (string $path, ?ActionContextInterface $context): string {
        $data    = $context?->toArray() ?? [];
        $allowed = ['status', 'department', 'page'];
        $params  = array_filter(
            array_intersect_key($data, array_flip($allowed)),
            static fn(mixed $v): bool => '' !== (string) $v,
        );
        return empty($params) ? '/employees' : '/employees?' . http_build_query($params);
    };
}
```

### 3. No context

If the path has no placeholders, pass no context or pass `DefaultActionContext::create([])`.

### Decision rule

| Scenario | Approach |
|---|---|
| Path segment — always required | YAML `{placeholder}` |
| Query params — all required | YAML `{placeholder}` in query string |
| Query params — any optional | `resolvePathCallback` + `http_build_query` |
| No dynamic values | No context |

---

## Mappers

A mapper transforms the raw response array into a typed `ResponseInterface`. The engine
validates `$mapper::getAction() === $action::class` before calling `transform()` — this
is a hard invariant enforced both in the engine and in `AbstractMapper::map()`.

```php
final class GetEmployeeMapper extends AbstractMapper
{
    public static function getAction(): string { return GetEmployeeAction::class; }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return GetEmployeeResponse::create(
            employee: Employee::create($response),
        );
    }
}
```

**One mapper per action.** You cannot share a mapper between two action classes — the
engine will throw `MapperActionMismatchException`. When two actions return the same
response shape, extract the transform logic into a dedicated class and delegate from
each mapper:

```php
// Shared logic
final class EmployeeCollectionTransformer
{
    public static function transform(array $response): GetEmployeesResponse { ... }
}

// Each mapper keeps its own getAction()
final class GetEmployeesMapper extends AbstractMapper
{
    public static function getAction(): string { return GetEmployeesAction::class; }
    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return EmployeeCollectionTransformer::transform($response);
    }
}

final class FilterEmployeesMapper extends AbstractMapper
{
    public static function getAction(): string { return FilterEmployeesAction::class; }
    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return EmployeeCollectionTransformer::transform($response);
    }
}
```

---

## Response DTOs

DTOs are `final readonly` classes. They reflect the external API — field names, nullability,
and types match what the API returns, not what your domain needs.

```php
final readonly class Employee
{
    private function __construct(
        public int    $id,
        public string $name,
        public string $department,
        public ?string $email,     // nullable when the API can omit it
    ) {}

    public static function create(array $data): self
    {
        return new self(
            id:         (int)    ($data['id']         ?? 0),
            name:       (string) ($data['name']       ?? ''),
            department: (string) ($data['department'] ?? ''),
            email:      isset($data['email']) && is_string($data['email']) ? $data['email'] : null,
        );
    }

    public function toArray(): array { ... }
}
```

**Type mapping from API responses:**

| API type | PHP type | Cast in `create()` |
|---|---|---|
| integer | `int` | `(int) ($data['x'] ?? 0)` |
| number | `float` | `(float) ($data['x'] ?? 0.0)` |
| boolean | `bool` | `(bool) ($data['x'] ?? false)` |
| string | `string` | `(string) ($data['x'] ?? '')` |
| array | `array` | `$data['x'] ?? []` |
| nullable string | `?string` | `is_string($data['x'] ?? null) ? $data['x'] : null` |
| nested object (simple) | `array` | `$data['x'] ?? []` |
| nested object (reused) | `DtoClass` | `DtoClass::create($data['x'] ?? [])` |

Create a dedicated DTO class for nested objects that appear in more than one response,
or that have more than three or four fields. Use `array` for simple, single-use objects.

**`toArray()` is an engine contract, not your public API.** The engine uses it internally
to extract dynamic auth tokens. Consumers of your facade should access typed properties
directly — never ask them to call `toArray()`.

---

## Authorization in depth

### Static

Declared in the YAML action entry. The `ResolvesAuthHeaders` trait in the HTTP adapters
translates the config to headers:

| Type | Required params | Header produced |
|---|---|---|
| `bearer` | `token`, optional `prefix` (default: `Bearer`) | `Authorization: Bearer {token}` |
| `basic` | `username`, `password` | `Authorization: Basic {base64}` |
| `api_key` | `token`, `header` (default: `X-Api-Key`) | `{header}: {token}` |

### Dynamic

The engine executes the auth action, extracts `token_field` from the response via
`ResponseInterface::toArray()`, and caches the result under the key:

```
integration_engine.token.{integrationName}.{authActionName}
```

On subsequent calls within the TTL, the cached token is used directly. The auth action
is never called again until the cache entry expires.

The token action is a regular action. It requires its own `Action`, `Mapper`, and
`Response`. The response `toArray()` must include the field named in `token_field`:

```php
final readonly class FetchTokenResponse implements ResponseInterface
{
    public function __construct(public readonly string $accessToken) {}

    // 'access_token' must match token_field in the YAML authorization block
    public function toArray(): array { return ['access_token' => $this->accessToken]; }
}
```

---

## Cache behaviour

The built-in `Psr6CacheAdapter` wraps any `CacheItemPoolInterface`. By default it uses
`cache.app`.

Under PHP-FPM, `cache.app` is process-local (filesystem or APCu). Each worker maintains
its own cache state. With N workers, the token endpoint will be called up to N times per
TTL window — once per worker on first warm-up. For most APIs this is acceptable.

For APIs with strict rate limits on the token endpoint, configure a shared Redis pool:

```yaml
# config/packages/integration_engine.yaml
integration_engine:
    integrations:
        my_api:
            cache_service: 'cache.my_api_tokens'

# config/packages/cache.yaml
framework:
    cache:
        pools:
            cache.my_api_tokens:
                adapter: cache.adapter.redis
                provider: 'redis://localhost'
```

---

## Anti-Corruption Layer

Integration DTOs must never reach the domain layer. The translation happens in an
application service that sits between the facade and the domain:

```
Controller → ApplicationService → IntegrationFacade → Engine
                ↓
           DomainObject ← (translation happens here)
```

If the external API changes a field name or type, only the DTO, its mapper, and the
application service's translation code need to change. Domain objects and domain logic
are unaffected.

---

## Custom HTTP client

Use `client_service` when you need full control over the HTTP layer for a specific
integration — retry logic, circuit breaking, custom logging, or test doubles:

```yaml
my_api:
    client_service: 'App\Infrastructure\Http\RetryingHttpClient'
```

```php
final class RetryingHttpClient implements ClientInterface
{
    public function send(AbstractAction $action, ?ActionContextInterface $context = null, ...): array
    {
        // retry on 429, circuit break on 503, etc.
    }
}
```

`client:` selects a registered protocol adapter (rest, graphql, soap) — the bundle
manages wiring. `client_service:` bypasses the adapter system entirely and injects your
service directly as the `ClientInterface`. The two options are mutually exclusive.
