# IntegrationEngine Bundle — Documentation

## 1. Architecture overview

The bundle implements a hexagonal integration engine:

- **Core**: contracts + engine logic
- **Infrastructure**: HTTP, YAML, cache adapters
- **Bundle**: Symfony wiring

## 2. Core execution model

```text
Registry
  -> IntegrationEngine
      -> ConfigPort (YAML / custom source)
      -> Action (immutable)
      -> Context binding (path resolution)
      -> Authorization (static or dynamic with cache)
      -> HTTP Client (YAML headers + auth headers + caller headers)
      -> Mapper
      -> Response DTO
```

## 3. Actions

An Action defines:

- HTTP method
- Path template (supports `{param}` placeholders)
- Optional body
- Optional authorization config
- Optional mapper

Actions are immutable. The engine clones them when applying context or
reconstructing them after dynamic auth resolution.

## 4. Context system

Context resolves dynamic URL path segments at call time:

```php
/orders/{id}
```

becomes:

```php
/orders/42
```

The context is provided at runtime via `ActionContextInterface`:

```php
->send(
    'GetOrder',
    context: GetOrderContext::create(['id' => 42])
)
```

Missing parameters throw a `RuntimeException` at resolution time, not at HTTP
time. Non-scalar values throw immediately.

A custom resolver can be provided by overriding `resolvePathCallback()` in the
Action:

```php
protected function resolvePathCallback(): ?callable
{
    return function (string $path, ?ActionContextInterface $context): string {
        // custom resolution logic
        return $resolvedPath;
    };
}
```

## 5. Body system

Bodies are explicit objects implementing `ActionBodyInterface`:

```php
final class CreateUserBody implements ActionBodyInterface
{
    public static function create(array $data): self {}

    public function toArray(): array {}
}
```

Bodies are serialised as JSON and sent for `POST`, `PUT`, `PATCH`, and
`DELETE` requests.

## 6. Authorization system

### Static authorization

Declared in the integration YAML or directly in the Action. Supported types:

| Type      | Header produced                     |
|-----------|-------------------------------------|
| `bearer`  | `Authorization: Bearer {token}`     |
| `basic`   | `Authorization: Basic {b64}`        |
| `api_key` | `{header}: {token}` (custom header) |

### Dynamic authorization

For APIs that require a pre-flight token request (OAuth client credentials,
session tokens, API key exchanges):

```yaml
authorization:
  type: dynamic
  action: FetchToken
  token_field: access_token
  ttl: 3600
```

The engine:

1. Checks the cache for `integration_engine.token.{action}`.
2. If absent, executes the auth action and extracts `token_field` from the
   response.
3. Caches the token for `ttl` seconds.
4. Substitutes a `StaticAuthorizationConfig` transparently before the actual
   request.

The integration author writes no caching logic.

## 7. Headers system

Headers are resolved in three layers. Each layer overrides the previous:

```
YAML defaults  →  Auth headers  →  Caller headers
```

### Layer 1 — YAML defaults

Fixed headers sent with every request for an integration. Declared in
`integration_engine.yaml`:

```yaml
integration_engine:
  integrations:
    stripe:
      base_url: 'https://api.stripe.com'
      headers:
        X-Api-Version: '2023-10-16'
        X-Client-Name: 'my-app'
```

Use for API versioning headers, client identification, or any header that is
fixed for the integration but not part of the auth contract.

### Layer 2 — Auth headers

Resolved by the engine from the Action's `AuthorizationConfig`. Always
override YAML defaults.

### Layer 3 — Caller headers

Per-request headers provided at call time. Implement `RequestHeadersInterface`
and pass as the `headers` parameter:

```php
final class CorrelationHeaders implements RequestHeadersInterface
{
    public function __construct(private readonly string $requestId) {}

    public function toArray(): array
    {
        return ['X-Correlation-ID' => $this->requestId];
    }
}

$registry->get('stripe')->send(
    'ChargeCard',
    context: $context,
    body: $body,
    headers: new CorrelationHeaders($requestId),
);
```

Use for correlation IDs, tenant identifiers, or any header that varies per
request.

## 8. Engine API

```php
send(
    string $actionName,
    ?ActionContextInterface $context = null,
    ?ActionBodyInterface $body = null,
    ?RequestHeadersInterface $headers = null,
): ResponseInterface
```

### Flow

1. Load action from ConfigPort
2. Apply context (path resolution)
3. Apply authorization (static injection or dynamic token resolution)
4. Execute HTTP request (YAML headers + auth headers + caller headers)
5. Return `EmptyResponse` if `hasResponse()` is false
6. Map response via mapper
7. Return typed `ResponseInterface`

## 9. YAML configuration

### Bundle configuration (`integration_engine.yaml`)

```yaml
integration_engine:
  integrations:
    my_api:
      base_url: '%env(MY_API_BASE_URL)%'
      config_path: '%kernel.project_dir%/src/Integration/MyApi/MyApi.yaml'
      headers:
        X-Api-Version: '2'
      cache_service: ~       # defaults to InMemoryCacheAdapter (dev only)
      client_service: ~      # custom ClientInterface service ID
```

Either `base_url` or `client_service` is required per integration.

> **Warning**: The default `InMemoryCacheAdapter` is process-scoped and does
> not persist between requests under PHP-FPM. Configure a `cache_service`
> backed by Redis or APCu for dynamic auth in production.

### Action configuration (`MyApi.yaml`)

```yaml
GetUsers:
  method: GET
  path: /users

GetUser:
  method: GET
  path: /users/{id}

CreateUser:
  method: POST
  path: /users
```

No logic lives in YAML. YAML declares intent; Actions and Mappers implement
behaviour.

## 10. Scaffolding

```bash
php bin/console make:integration MyApi GetUsers
```

Generates:

- `GetUsersAction.php`
- `GetUsersMapper.php`
- `GetUsersResponse.php`
- `my_api.yaml` (or appends to existing)

The scaffolding command is the recommended way to create integrations. It
fills in all required values — including the integration name — so that the
generated classes are immediately valid and registered correctly.

### Creating integrations manually

If you create an integration class by hand without using the command, you must
override the `NAME` constant in your class:

```php
final class StripeIntegration implements IntegrationName
{
    public const string NAME = 'stripe'; // must be declared explicitly
}
```

The interface declares `NAME = '__MUST_OVERRIDE__'` as a sentinel value. PHP
does not enforce constant overriding at the language level, so the bundle
cannot detect a missing override at compile time. If `NAME` is not declared,
the integration will be registered under `'__MUST_OVERRIDE__'` and will not
resolve correctly from the registry.

This is a one-time declaration per integration. The scaffolding handles it
automatically; manual creation requires it explicitly.

## 11. Extensibility

Every infrastructure component is replaceable via interfaces:

| Contract            | Default implementation         | Override via          |
|---------------------|--------------------------------|-----------------------|
| `ClientInterface`   | `SymfonyHttpClientAdapter`     | `client_service`      |
| `CachePort`         | `InMemoryCacheAdapter`         | `cache_service`       |
| `ConfigPort`        | `YamlConfigAdapter`            | custom CompilerPass   |

## 12. Design principles

- No magic outside the engine
- Actions are immutable
- Context is explicit and validated at resolution time
- Bodies are typed objects
- Mapping is explicit via mappers
- Headers have a defined precedence: YAML → auth → caller
- The call site is uniform regardless of integration complexity

## 13. Recommended usage pattern

The bundle can be used by calling `IntegrationRegistry` directly from any
Symfony service. However, the recommended pattern is to wrap each integration
in a dedicated facade class. This is not the only correct way to use the
bundle, but it is the one that best expresses its intent.

### The facade pattern

Create one class per external integration. That class resolves the engine
once in the constructor and exposes named methods for each operation:

```php
final class OrdersApiIntegration implements IntegrationName
{
    public const string NAME = 'orders_api';

    private IntegrationEngine $engine;

    public function __construct(IntegrationRegistry $registry)
    {
        $this->engine = $registry->get(self::NAME);
    }

    public function getOrder(int $id): GetOrderResponse
    {
        return $this->engine->send(
            GetOrderAction::getName(),
            context: GetOrderContext::create(['id' => $id]),
        );
    }

    public function createOrder(CreateOrderBody $body): CreateOrderResponse
    {
        return $this->engine->send(
            CreateOrderAction::getName(),
            body: $body,
        );
    }
}
```

The consumer — a controller, a command, another service — only depends on
`OrdersApiIntegration`. It has no knowledge of the registry, the engine,
the HTTP client, or the auth mechanism:

```php
final class OrderController extends AbstractController
{
    public function __construct(
        private readonly OrdersApiIntegration $ordersApi,
    ) {}

    #[Route('/orders/{id}')]
    public function show(int $id): JsonResponse
    {
        $order = $this->ordersApi->getOrder($id);

        return $this->json([
            'id'     => $order->getId(),
            'status' => $order->getStatus(),
        ]);
    }
}
```

### Why this pattern

**The bundle disappears from the consumer.** The controller imports nothing
from `IntegrationEngine\`. The external dependency is invisible above the
integration layer.

**The facade is the domain boundary.** Method names speak business language
(`getOrder`, `createOrder`), not transport language (`send`, `GET`, `POST`).
If the external API changes its contract, only the facade and its Actions
change — nothing above it.

**One engine resolution, many calls.** The registry lookup happens once in
the constructor. Subsequent calls to `getOrder()` or `createOrder()` go
directly to the engine without re-resolving the service.

**Testability.** Any consumer that depends on `OrdersApiIntegration` can be
tested by replacing it with a fake or a mock. No need to stub the registry,
the engine, or the HTTP client.

### When this pattern does not apply

A dedicated facade adds a layer that is not always justified. Direct use of
the registry is acceptable when:

- The integration is called from a single place and is unlikely to grow.
- The project is a small script or a CLI command with no test requirements.
- You are prototyping and the facade would slow you down.

The goal is not to follow the pattern for its own sake, but to keep external
complexity out of the domain. Use the facade when that separation has value;
skip it when it does not.