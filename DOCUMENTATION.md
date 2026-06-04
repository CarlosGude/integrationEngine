# IntegrationEngine Bundle — Documentation

## Quick start

### 1. Install

```bash
composer require carlosgude/integration-engine
```

If Symfony Flex does not auto-register the bundle, add it manually to
`config/bundles.php`:

```php
return [
    // ...
    IntegrationEngine\Bundle\IntegrationEngineBundle::class => ['all' => true],
];
```

### 2. Generate your first integration

The `make:integration` command is all you need. No config file required
beforehand — the command creates it for you:

```bash
php bin/console make:integration DummyRestApi GetEmployees
```

The command asks three questions:

1. **Base URL** (only on first run, when no config exists yet):
   `https://dummy.restapiexample.com`
2. **Path for the action**: `/api/v1/employees`
3. **HTTP method**: `GET`

It then generates everything:

```
config/packages/integration_engine.yaml          ← created on first run
src/Infrastructure/Integrations/DummyRestApi/
    DummyRestApiIntegration.php
    DummyRestApi.yaml
    GetEmployees/
        Request/GetEmployeesAction.php
        Response/GetEmployeesMapper.php
        Response/GetEmployeesResponse.php
```

### 3. Call it

```php
use IntegrationEngine\Core\Contract\DefaultActionContext;

$registry->get('dummy_rest_api')->send(
    actionName: 'GetEmployee',
    context: DefaultActionContext::create(['id' => 1]),
);
```

That is all the bundle requires. Everything else in this document is optional
depth.

---

## 1. Architecture overview

The bundle implements a hexagonal integration engine:

- **Core**: contracts + engine logic
- **Infrastructure**: HTTP, YAML, cache adapters
- **Bundle**: Symfony wiring

## 2. Core execution model

The caller never talks to the HTTP layer directly. Every request goes through
the engine, which is responsible for resolving auth, applying context, and
mapping the response. The caller only knows the action name and gets back a
typed object.

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

Actions are stateless and immutable. All constructor properties are `readonly`.
The engine passes context directly to `getPath()` at call time — the action
never stores execution state. The same action instance can be called with
different contexts in successive requests without any mutation.

## 4. Context system

Context resolves dynamic URL path segments at call time:

```php
/orders/{id}
```

becomes:

```php
/orders/42
```

### DefaultActionContext — built-in implementation

The bundle ships with `DefaultActionContext`, a general-purpose implementation
of `ActionContextInterface` that covers the vast majority of cases:

```php
use IntegrationEngine\Core\Contract\DefaultActionContext;

->send(
    actionName: 'GetOrder',
    context: DefaultActionContext::create(['id' => 42]),
)
```

No custom class needed. No boilerplate. The contract stays uniform —
`DefaultActionContext` implements `ActionContextInterface` — while keeping
the call site as simple as possible.

### Custom context classes

For contexts with validation, domain semantics, or complex resolution logic,
implement `ActionContextInterface` directly:

```php
final readonly class GetOrderContext implements ActionContextInterface
{
    private function __construct(
        private int $orderId,
        private string $warehouseId,
    ) {}

    public static function create(array $data): self
    {
        return new self(
            orderId: (int) $data['id'],
            warehouseId: (string) $data['warehouse'],
        );
    }

    public function toArray(): array
    {
        return [
            'id'        => $this->orderId,
            'warehouse' => $this->warehouseId,
        ];
    }
}
```

Use a custom class when the context has logic beyond key-value storage:
validation, type coercion, or domain rules. For everything else,
`DefaultActionContext` is sufficient.

### Path resolution

Missing parameters throw a `RuntimeException` at resolution time, not at
HTTP time. Non-scalar values throw immediately.

A custom resolver can be provided by overriding `resolvePathCallback()` in
the Action:

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
final class CreateOrderBody implements ActionBodyInterface
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
    orders_api:
      base_url: 'https://api.example.com'
      headers:
        X-Api-Version: '2'
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

$registry->get('orders_api')->send(
    'CreateOrder',
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

### Getting a typed response

`send()` returns `ResponseInterface`. Use `assert()` to narrow the type for
PHPStan without adding runtime overhead:

```php
public function getOrder(int $id): GetOrderResponse
{
    $response = $this->engine->send(
        actionName: GetOrderAction::getName(),
        context: DefaultActionContext::create(['id' => $id]),
    );

    \assert($response instanceof GetOrderResponse);

    return $response;
}
```

## 9. YAML configuration

These are two separate files with different responsibilities. The bundle
configuration registers integrations in the Symfony container. The action
configuration declares the operations available for each integration.

### Bundle configuration (`config/packages/integration_engine.yaml`)

Registers integrations in the Symfony container and configures their
transport layer. **This file is created automatically by `make:integration`
on first run.** No need to create it manually.

```yaml
integration_engine:
  integrations:
    my_api:
      base_url: '%env(MY_API_BASE_URL)%'
      config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/MyApi/MyApi.yaml'
      headers:
        X-Api-Version: '2'
      cache_service: ~       # defaults to InMemoryCacheAdapter (dev only)
      client_service: ~      # custom ClientInterface service ID
```

Either `base_url` or `client_service` is required per integration. The
`integrations` key is optional — the bundle boots without it and fails only
when an integration is actually requested.

> **Warning**: The default `InMemoryCacheAdapter` is process-scoped and does
> not persist between requests under PHP-FPM. Configure a `cache_service`
> backed by Redis or APCu for dynamic auth in production.

### Action configuration (`src/Infrastructure/Integrations/MyApi/MyApi.yaml`)

Declares the operations available for a specific integration. **This file is
also generated by `make:integration`.** Each entry must include the fully
qualified class name of the Action:

```yaml
GetUsers:
  action: App\Infrastructure\Integrations\MyApi\GetUsers\Request\GetUsersAction
  method: GET
  path: /users

GetUser:
  action: App\Infrastructure\Integrations\MyApi\GetUser\Request\GetUserAction
  method: GET
  path: /users/{id}

CreateUser:
  action: App\Infrastructure\Integrations\MyApi\CreateUser\Request\CreateUserAction
  method: POST
  path: /users
```

The `action` key is how the engine resolves which PHP class to instantiate
for each named operation. No logic lives in YAML — YAML declares intent;
Actions and Mappers implement behaviour.

## 10. Scaffolding

```bash
php bin/console make:integration {IntegrationName} {ActionName}
```

### What the command does

| Step | What happens |
|------|-------------|
| First run only | Asks for the base URL and creates `config/packages/integration_engine.yaml` |
| Always | Asks for the action path and HTTP method |
| First run only | Creates `{Name}Integration.php` |
| Always | Creates `{Action}Action.php`, `{Action}Mapper.php`, `{Action}Response.php` |
| Always | Appends the action entry to `{Name}.yaml` (creates it if absent) |

### Example

```bash
php bin/console make:integration DummyRestApi GetEmployees
# > Base URL: https://dummy.restapiexample.com
# > Path: /api/v1/employees
# > Method: GET
```

Generates this entry in `DummyRestApi.yaml`:

```yaml
GetEmployees:
  action: App\Infrastructure\Integrations\DummyRestApi\GetEmployees\Request\GetEmployeesAction
  method: GET
  path: /api/v1/employees
```

### Adding a second action to an existing integration

```bash
php bin/console make:integration DummyRestApi GetEmployee
# > Path: /api/v1/employee/{id}
# > Method: GET
```

The command detects that `DummyRestApiIntegration.php` already exists and
skips generating it. Only the new action files are created and the YAML
entry is appended.

### Creating integrations manually

If you create an integration class by hand without using the command, you must
override the `NAME` constant in your class:

```php
final class MyApiIntegration implements IntegrationName
{
    public const string NAME = 'my_api'; // must be declared explicitly
}
```

The interface declares `NAME = '__MUST_OVERRIDE__'` as a sentinel value. PHP
does not enforce constant overriding at the language level, so the bundle
cannot detect a missing override at compile time. If `NAME` is not declared,
the integration will be registered under `'__MUST_OVERRIDE__'` and will not
resolve correctly from the registry.

The scaffolding handles this automatically; manual creation requires it
explicitly.

## 11. Extensibility

Every infrastructure component is replaceable via interfaces:

| Contract            | Default implementation         | Override via          |
|---------------------|--------------------------------|-----------------------|
| `ClientInterface`   | `SymfonyHttpClientAdapter`     | `client_service`      |
| `CachePort`         | `InMemoryCacheAdapter`         | `cache_service`       |
| `ConfigPort`        | `YamlConfigAdapter`            | custom CompilerPass   |

## 12. Error reference

The bundle throws named exceptions. All are catchable at the call site.

| Exception                        | When it is thrown                                                                 | Recommended action                                              |
|----------------------------------|-----------------------------------------------------------------------------------|-----------------------------------------------------------------|
| `ActionNotFoundException`        | `send()` is called with an action name not declared in the YAML config            | Verify the action name matches the YAML key exactly             |
| `NotMappedActionException`       | The action's `mapper()` returns `null` but `hasResponse()` is `true`             | Declare a mapper class in the Action or set `hasResponse: false`|
| `InvalidMapperException`         | `mapper()` returns a class that does not extend `AbstractMapper`                  | Check the mapper class declaration                              |
| `MapperActionMismatchException`  | The mapper's `getAction()` does not match the action being executed               | Ensure each mapper declares the correct Action class            |
| `RequestResponseException`       | The HTTP request returns a 4xx/5xx status, or a network error occurs              | Inspect `getStatusCode()` and `getContext()` for details        |
| `RuntimeException`               | A path parameter declared in the template is missing from the context             | Ensure all `{param}` placeholders are covered by the context    |
| `RuntimeException`               | A dynamic auth response does not contain the expected `token_field`               | Verify the auth action response structure matches the config    |

## 13. Design principles

- No magic outside the engine
- Actions are immutable
- Context is explicit and validated at resolution time
- Bodies are typed objects
- Mapping is explicit via mappers
- Headers have a defined precedence: YAML → auth → caller
- The call site is uniform regardless of integration complexity

## 14. Recommended usage pattern

The bundle can be used by calling `IntegrationRegistry` directly from any
Symfony service. However, the recommended pattern is to wrap each integration
in a dedicated facade class. This is not the only correct way to use the
bundle, but it is the one that best expresses its intent.

### The facade pattern

Create one class per external integration. That class resolves the engine
once in the constructor and exposes named methods for each operation:

```php
use IntegrationEngine\Core\Contract\DefaultActionContext;

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
        $response = $this->engine->send(
            actionName: GetOrderAction::getName(),
            context: DefaultActionContext::create(['id' => $id]),
        );

        \assert($response instanceof GetOrderResponse);

        return $response;
    }

    public function createOrder(CreateOrderBody $body): CreateOrderResponse
    {
        $response = $this->engine->send(
            actionName: CreateOrderAction::getName(),
            body: $body,
        );

        \assert($response instanceof CreateOrderResponse);

        return $response;
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
the constructor. Subsequent calls go directly to the engine without
re-resolving the service.

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

## 15. The bundle proposes, it does not impose

IntegrationEngine defines contracts. What you build on top of them is entirely
up to you.

Every piece of the bundle is a suggestion, not a requirement:

- Use `DefaultActionContext` for simple path parameters, or implement
  `ActionContextInterface` for validation and domain logic.
- Declare auth in YAML for simple cases, use `DynamicAuthorizationConfig`
  for token flows, or centralise it in a base action class.
- Use the generated scaffold as-is, or extend it with value objects,
  typed collections, and domain facades.
- Replace any infrastructure component — client, cache, config source —
  via a single config key.

The bundle never sees beyond `AbstractAction`, `ActionContextInterface`, and
`ResponseInterface`. Everything else is your domain.

### Integration base classes

The most powerful pattern the bundle enables without knowing about it is the
integration base class. If a group of actions shares configuration — auth,
a path prefix, common headers — extract it into an abstract class that extends
`AbstractAction`:

```php
abstract class StripeAction extends AbstractAction
{
    public static function create(
        string $method,
        string $path,
        ?ActionBodyInterface $body = null,
    ): static {
        return parent::create(
            method: $method,
            path: '/v1'.$path,
            body: $body,
            authorization: new StaticAuthorizationConfig(
                type: 'bearer',
                params: ['token' => '%env(STRIPE_SECRET_KEY)%'],
            ),
        );
    }
}
```

Every Stripe action then extends this base. Auth and path prefix are declared
once. Each action only declares what makes it unique:

```php
final class CreateChargeAction extends StripeAction
{
    public static function getName(): string { return 'CreateCharge'; }
    public static function hasResponse(): bool { return true; }
    public static function mapper(): string { return CreateChargeMapper::class; }
}

final class ListCustomersAction extends StripeAction
{
    public static function getName(): string { return 'ListCustomers'; }
    public static function hasResponse(): bool { return true; }
    public static function mapper(): string { return ListCustomersMapper::class; }
}
```

The bundle sees `AbstractAction`. Your domain sees `StripeAction`. The
abstraction holds at both levels without any coupling between them.

### The three levels of action design

| Level | Class | Responsibility |
|-------|-------|----------------|
| Bundle | `AbstractAction` | Contract: method, path, auth, mapper |
| Integration | `StripeAction` | Shared config: auth, prefix, defaults |
| Operation | `CreateChargeAction` | Identity: name, response, mapper |

Use one level, two, or all three. The bundle works the same either way.

### What this means in practice

A junior developer on your team can write a new Stripe action in three lines
without knowing anything about authentication, HTTP clients, or token caching.
A senior can replace the entire HTTP layer without touching a single action.
The architect can define contracts that the whole team follows without writing
a framework.

That is the goal: a tool that gives structure without restricting freedom.

---

## 16. Demo project

A working Symfony application demonstrating the bundle against the public
[Dummy REST API](https://dummy.restapiexample.com) is available at:

**[github.com/CarlosGude/integrationEngine-use-example](https://github.com/CarlosGude/integrationEngine-use-example)**

The demo implements two endpoints (`GET /employees` and `GET /employee/{id}`)
and shows the full stack in practice:

- Bundle configuration generated by `make:integration`
- Value objects and typed collections built on top of the scaffold
- Facade pattern wrapping the engine
- Thin controllers with no knowledge of the HTTP layer

It is the recommended starting point for understanding how the bundle fits
into a real Symfony application.