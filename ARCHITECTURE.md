# Architecture decisions

This document explains the *why* behind IntegrationEngine's design. It assumes you have
read the README and can already install and run the bundle. What it covers are the
non-obvious decisions that affect how you write integrations — and what goes wrong when
you work against them.

---

## 1. Actions are stateless and immutable

`AbstractAction` is `final readonly` on all its properties. Once the engine instantiates
an action via `Action::create()`, nothing changes it. This is intentional.

**Why it matters.** The engine resolves path parameters, authorization, and body at call
time — not at construction time. If an action carried mutable state (e.g. an `$id`
property set in the constructor), two concurrent requests could race on the same instance.
`readonly` makes that class of bug impossible.

**The practical rule.** Dynamic values (path params, filter values, request-specific
headers) never go into the action constructor. They travel via `ActionContextInterface`,
`ActionBodyInterface`, or `RequestHeadersInterface`, all of which are created fresh per
call.

```php
// ✔ Correct — action is stateless, context carries the id
$engine->send(GetCharacterAction::getName(), DefaultActionContext::create(['id' => 42]));

// ❌ Wrong — don't put runtime values in the action constructor
final class GetCharacterAction extends AbstractAction
{
    public function __construct(private int $id) {} // never do this
}
```

---

## 2. Path resolution — three approaches and when to use each

The engine resolves the path in `AbstractAction::getPath()`. There are three ways to
influence it, and choosing the wrong one is the most common integration mistake.

### 2a. YAML path with `{placeholder}` — required params

`YamlConfigAdapter` passes the raw path string from the YAML entry to `Action::create()`.
The `defaultResolvePath` method applies a regex (`/\{(\w+)\}/`) to the **full string**,
including any query string portion. Every placeholder it finds must be present in the
context, or it throws `PathResolutionException::missingParameter`.

Use this when all parameters are guaranteed to be present at call time:

```yaml
GetCharacter:
    path: /character/{id}             # segment param — always required

FilterByStatus:
    path: /character?status={status}  # query param — only when always required
```

### 2b. Custom context with `resolvePath()` — optional or computed params

`PathResolvableContextInterface` (extends `ActionContextInterface`) declares
`resolvePath(string $path): ?string`. When a context implementing it returns a non-null
string, the engine uses it directly and skips `defaultResolvePath`. When it returns
`null`, the engine falls back to the default `{placeholder}` resolver.

This is where optional query string logic lives — in the context, not in the action:

```php
final readonly class FilterCharactersContext implements PathResolvableContextInterface
{
    private const array ALLOWED = ['name', 'status', 'species', 'gender', 'page'];

    private function __construct(private array $filters) {}

    public static function fromFilters(array $filters): self { return new self($filters); }
    public static function create(array $data): self         { return new self($data); }
    public function toArray(): array                         { return $this->filters; }

    public function resolvePath(string $path): ?string
    {
        $params = array_filter(
            array_intersect_key($this->filters, array_flip(self::ALLOWED)),
            static fn(mixed $v): bool => '' !== (string) $v,
        );
        // null → engine uses raw path "/character" unchanged
        return empty($params) ? null : $path . '?' . http_build_query($params);
    }
}
```

The action itself stays purely declarative — no path logic, no callbacks:

```php
final class FilterCharactersAction extends AbstractAction
{
    public static function getName(): string   { return 'FilterCharacters'; }
    public static function hasResponse(): bool { return true; }
    public static function mapper(): ?string   { return FilterCharactersMapper::class; }
}
```

### 2c. `DefaultActionContext` — nothing to decide

`DefaultActionContext` does not implement `PathResolvableContextInterface`, so the
default `{placeholder}` resolver always applies. Use it for actions where all params are
required path segments or where no dynamic params exist at all.

### Decision rule

| Params | All required? | Approach |
|---|---|---|
| Path segment (`/{id}`) | Yes, by definition | YAML placeholder + `DefaultActionContext` |
| Query string | All required | YAML placeholder in query string + `DefaultActionContext` |
| Query string | Any optional | Custom context with `resolvePath()` |
| No params | — | No context needed |

---

## 3. One mapper per action — and why you cannot share them

`IntegrationEngine::applyMapper()` enforces a hard invariant before delegating to
`AbstractMapper::map()`:

```php
if ($mapperClass::getAction() !== $action::class) {
    throw new MapperActionMismatchException(...);
}
```

`AbstractMapper::map()` repeats the same check as a public contract for callers outside
the engine. Both guards fire — there is no way around them.

**The consequence.** Two action classes with identical response shapes still need two
mapper classes. The check uses `$action::class` (the concrete class), so
`FilterCharactersAction` and `GetAllCharactersAction` are different keys even if their
response arrays look identical.

**The right pattern for shared transform logic** is to extract it into a static method,
a trait, or a base class, and have each mapper's `transform()` delegate to it:

```php
// Shared logic
final class CharacterCollectionTransformer
{
    public static function transform(array $response): GetAllCharactersResponse
    {
        $characters = array_map(
            static fn(array $item): Character => Character::create($item),
            $response['results'] ?? [],
        );
        return GetAllCharactersResponse::create(
            info: PaginationInfo::create($response['info'] ?? []),
            characters: $characters,
        );
    }
}

// Each mapper keeps its own getAction() and delegates
final class GetAllCharactersMapper extends AbstractMapper
{
    public static function getAction(): string { return GetAllCharactersAction::class; }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return CharacterCollectionTransformer::transform($response);
    }
}

final class FilterCharactersMapper extends AbstractMapper
{
    public static function getAction(): string { return FilterCharactersAction::class; }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return CharacterCollectionTransformer::transform($response);
    }
}
```

This keeps the engine's invariant intact while avoiding duplicated transform logic.

---

## 4. Dynamic auth cache — what it is and what it is not

When an action uses `DynamicAuthorizationConfig`, the engine calls the auth action once,
extracts the token from the response via `tokenField`, and caches it under the key
`integration_engine.token.{integrationName}.{authActionName}`.

**Stale token handling.** A token can be revoked or expire server-side before its TTL.
When a request fails with HTTP 401 and the token came from the cache, the engine deletes
the entry, fetches a fresh token, and retries the request exactly once. A fresh token
that is rejected propagates the 401 — refetching would yield the same token. Non-401
errors never evict the token: a 500 says nothing about its validity.

The cache backend is whatever `CachePort` implementation is wired for that integration.
The built-in `Psr6CacheAdapter` wraps any `CacheItemPoolInterface` — typically `cache.app`.

**What `cache.app` is under PHP-FPM.** Symfony's `cache.app` defaults to the filesystem
adapter in most setups, and to APCu in others. Both are **process-local and
worker-local**: each FPM worker has its own cache state. With 8 workers and a 3600s TTL,
the auth endpoint will be called up to 8 times per TTL window — once per worker on first
warm-up. Under load this is usually acceptable. Under strict rate limits it is not.

**When to use a dedicated pool.** If the external API enforces a low rate limit on its
token endpoint (e.g. one call per minute), configure `cache_service` in
`integration_engine.yaml` to point to a shared Redis or Memcached pool:

```yaml
integration_engine:
    integrations:
        my_api:
            base_url: 'https://api.example.com'
            config_path: '...'
            cache_service: 'cache.my_api_tokens'  # a dedicated Redis pool
```

Then define that service in `config/packages/cache.yaml`:

```yaml
framework:
    cache:
        pools:
            cache.my_api_tokens:
                adapter: cache.adapter.redis
                provider: 'redis://localhost'
```

**The in-memory adapter** (`CachePort` implemented as a plain array) is only appropriate
for integration tests or local development. Never use it in production with dynamic auth.

---

## 5. `DefaultActionContext` vs a custom `ActionContextInterface`

`DefaultActionContext` is a transparent key-value wrapper. It does no validation, no
type coercion, and carries no domain meaning. It is the right choice for the vast majority
of cases.

Implement a custom `ActionContextInterface` when you need one or more of these:

**Validation at construction.** If passing an invalid value would produce a silent bug
(a negative page number, a malformed UUID, an empty required filter), put the validation
in the context constructor rather than in the action or the calling code:

```php
final readonly class GetCharacterContext implements ActionContextInterface
{
    private function __construct(private int $id) {}

    public static function create(array $data): self
    {
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            throw new \InvalidArgumentException('Character id must be a positive integer.');
        }
        return new self(id: $id);
    }

    public function toArray(): array { return ['id' => $this->id]; }
}
```

**Typed construction from domain objects.** When the calling code works with domain
objects (an `OrderId` value object, a `UserId`), a typed context avoids the
`['id' => $orderId->value()]` dance at every call site:

```php
public static function fromOrderId(OrderId $id): self
{
    return new self(id: $id->value());
}
```

**The rule of thumb.** If you find yourself validating or casting the array before passing
it to `DefaultActionContext::create()`, or building a query string before calling
`engine->send()`, that logic belongs in a custom context class.

---

## 6. DTOs are infrastructure, not domain

Response DTOs implement `ResponseInterface` and live under
`src/Infrastructure/Integrations/{Name}/`. They reflect the external API's data shape —
field names, nullability, and types match what the API returns, not what your domain needs.

This has two consequences:

**Do not pass DTOs to domain services or aggregate them into domain objects.** The
translation happens in an application service that sits between the integration facade
and the domain. If the external API renames a field, only the DTO and its mapper need
to change — the domain is unaffected.

```php
// Application service — the only place that knows about both sides
final class CharacterService
{
    public function __construct(
        private readonly RickAndMortyIntegration $integration,
    ) {}

    public function find(int $id): Character // domain object
    {
        $dto = $this->integration->getCharacter($id); // infrastructure DTO

        return new Character(
            id:   $dto->character->id,
            name: $dto->character->name,
        );
    }
}
```

**`toArray()` is an engine contract, not your public API.** `ResponseInterface` requires
`toArray()` because the engine uses it internally to extract dynamic auth tokens. Expose
typed properties on your DTO class for everything else — never ask consumers to call
`toArray()` on a response.

---

## 7. The `ConfigPort` / `ClientInterface` separation

The engine depends on two ports, not one:

- `ConfigPort::getAction()` — resolves an action class and instantiates it with method,
  path, body, and authorization from configuration.
- `ClientInterface::send()` — executes the HTTP request and returns a raw array.

Keeping them separate means you can replace either independently. The most common case
is replacing `ClientInterface` — to add retry logic, circuit breaking, or custom logging
— without touching how actions are configured:

```yaml
my_api:
    client_service: 'App\Infrastructure\Http\RetryingHttpClient'
```

Use `client:` to select a protocol adapter (rest, graphql, or a custom registered type).
Use `client_service:` when you want total control over the HTTP layer for a specific
integration. The two options are mutually exclusive per integration.

## 8. Dynamic `base_url` — why it's opt-in and doesn't break the invariant

`base_url` historically lived only at build-time, fixed on the HTTP adapter when the
container compiles — the same argument as static `client_service:`: simplicity for the
common case, where an integration always targets the same host.

Passing an optional `baseUrl` to `send()`/`sendMany()` is not a new concept: it follows
the same pattern `RequestHeadersInterface` already uses for per-request dynamic auth — a
value that varies per call gets injected at the engine's entry point instead of fixed in
config, and only the client decides whether to use it. What changed is that `base_url`
had been left out of that pattern while the auth token already followed it.

The capability is exposed through `DynamicBaseUrlClientInterface`, not as a mandatory
signature change on `ClientInterface`. The engine checks `instanceof` before using it: a
client that doesn't implement it simply receives the `baseUrl` and ignores it — no
exception, no breakage. This is deliberate: third-party custom adapters that don't need
dynamic URLs don't have to implement anything new to keep compiling.

In `sendMany()`, requests in the same batch are grouped by resolved `baseUrl` before
dispatch: each group runs through a single client (concurrently if it implements
`BatchClientInterface`), preserving the original batch's keys. This avoids a batch with
mixed URLs silently degrading all concurrency into sequential sends.

## 9. Middleware pipeline — observability, caching, and extensibility

Every outgoing request passes through `MiddlewareClient` before reaching the HTTP adapter.
`MiddlewareClient` holds an ordered list of `ClientMiddlewareInterface` implementations
and chains them into a pipeline: middleware[0] is outermost (first to execute, last to
return); the HTTP adapter is the terminal node.

**Why a pipeline, not decorator pairs.** The previous design used `TraceableClient` and
`TraceableBatchClient` as separate decorator classes. Every new cross-cutting concern
required two new classes, and each decorator had to replicate every optional capability
interface (`BatchClientInterface`, `DynamicBaseUrlClientInterface`) of the client it
wrapped. Missing one caused a silent downgrade — dynamic `base_url` silently broke
whenever `kernel.debug` was true, because `TraceableClient` had not implemented
`DynamicBaseUrlClientInterface`. A pipeline collapses this: one `MiddlewareClient` always
implements every capability interface; individual middlewares only implement `process()`
and, optionally, `processMany()`.

**Fixed layer invariants.** Two positions in the chain are non-negotiable:

- `CachingMiddleware` is always outermost. A cache hit must bypass all downstream layers —
  including user middlewares — or they pay overhead for a request that never goes over the
  wire.
- `TracingMiddleware` is always the innermost built-in (debug only). It sits as close to
  the HTTP adapter as possible so it measures the actual network call, not the time spent
  in user middlewares. Cache hits are recorded separately by `CachingMiddleware` itself
  with a duration of 0 ms.

User middlewares are injected between these two anchors, ordered by descending `priority`
(higher = outermost). The `priority` attribute on the `integration_engine.middleware` tag
follows the same convention as Symfony event listeners and kernel middleware — no new
mental model needed.

**`IntegrationEngine` stays untouched.** Caching, tracing, and any user-added concerns
are all outside the engine's scope. The Mapper Invariant, dynamic-auth retry logic, and
batch orchestration remain in `IntegrationEngine` without observability noise. The
middleware chain is the right extension point precisely because it sits at the HTTP
boundary, not at the engine boundary.

**Tracing opt-in conditions.** `TracingMiddleware` is wired only when `kernel.debug` is
true, `DataCollectorInterface` exists (checks for `symfony/http-kernel`), and a `profiler`
service is actually registered. The last check matters: `symfony/http-kernel` alone is
present in nearly every Symfony project; without `symfony/web-profiler-bundle` nothing
would read the collected data. A project that omits the web profiler bundle pays no cost.

**Batch timing trade-off.** In `processMany()`, `TracingMiddleware` records one duration
per item but uses the total elapsed time for the whole batch — with a concurrent batch you
cannot isolate per-request latencies. The recorded duration is accurate for sequential
batches and represents wall-clock time per item for concurrent ones. This is accepted
deliberately: the profiler panel is useful for identifying slow integrations, not for
sub-millisecond per-request profiling.