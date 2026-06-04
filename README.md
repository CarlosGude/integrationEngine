# IntegrationEngine Bundle

A Symfony bundle for centralising external API integrations behind a consistent, hexagonal architecture.

> **Full documentation** → [DOCUMENTATION.md](./DOCUMENTATION.md)

---

## Motivation and differentiation

### The problem

Every Symfony application that consumes external APIs faces the same structural decision: where does the HTTP integration logic live, and how does it stay testable, replaceable, and consistent as the number of integrations grows?

The typical answer evolves through three stages. First, `HttpClient` calls are made inline inside services — fast to write, impossible to test in isolation, and impossible to replace. Second, a dedicated client class is introduced per integration — better, but the shape of each client is invented from scratch each time, and authentication, caching, and mapping logic accumulates inside those classes without a shared contract. Third, the team adopts a library or writes a framework.

This bundle addresses the third stage.

### Existing alternatives

**Saloon (PHP)**
Saloon is the most complete PHP HTTP integration library available. It provides connectors, requests, plugins, and response handling with a mature ecosystem. It is the right choice for projects that need maximum flexibility, plugin extensibility, and a large community. IntegrationEngine is not a competitor to Saloon in scope — it is narrower by design.

The specific difference is architectural: Saloon encourages extending base classes to define connectors and requests, which places integration logic inside class hierarchies that are defined by the developer. IntegrationEngine inverts this — the engine drives the flow, and integrations are configuration plus thin POPOs. The developer never writes a method that calls `$this->send()`. The result is a more constrained surface that is easier to reason about in a hexagonal architecture, at the cost of the flexibility Saloon provides.

**Symfony HttpClient with scopes**
Symfony's HttpClient supports scoped clients via `framework.http_client.scoped_clients`, which bind a base URL, headers, and auth to a named service. This solves the base URL and static auth problem well. It does not solve the action-level contract problem: there is no shared interface that a service calls to trigger a named operation, no mapper contract that enforces response shaping, no dynamic auth flow, and no context system for path parameter resolution. Scoped clients are infrastructure; IntegrationEngine is an application-layer contract on top of infrastructure.

**Handwritten clients with discipline**
A skilled team can write HTTP client classes that implement an interface, use a mapper, and inject cache and auth manually. This works. The cost is that every client is a unique snowflake — the auth caching logic is reimplemented, the mapper pattern is reinvented, the YAML configuration is hand-parsed. IntegrationEngine codifies those decisions once and makes the pattern enforced rather than conventional.

### What this bundle specifically solves

Three problems that none of the above address together:

**1. Dynamic authorization with transparent caching.** Many APIs require a pre-flight token request — OAuth client credentials, session tokens, API key exchanges. In handwritten clients this logic is duplicated per integration or pulled into a shared service that all clients depend on. In IntegrationEngine, `DynamicAuthorizationConfig` declares which action fetches the token and which field contains it. The engine handles the request, caches the result for the declared TTL, and substitutes a `StaticAuthorizationConfig` transparently before the actual request. The integration author writes no caching logic.

**2. Path context as a first-class concept.** URL path parameters (`/orders/{id}`) are not query parameters and are not body fields. They require resolution at call time with caller-supplied data. IntegrationEngine's `ActionContextInterface` makes this contract explicit: the caller declares what parameters they are providing, and the engine resolves the path. A missing parameter throws at resolution time, not at HTTP time. A non-scalar parameter throws at resolution time, not silently passes a stringified array.

**3. A uniform call site regardless of integration complexity.** Whether the integration has no auth, static bearer auth, or dynamic OAuth — whether it has a body or no body, path parameters or no parameters — the call site is always:

```php
$registry->get(AcmeIntegration::NAME)->send(GetUsersAction::getName(), context: ..., body: ...);
```

This uniformity means services that call integrations are decoupled from the authentication and transport complexity of each integration. A service does not know or care whether fetching a token is involved.

### What this bundle deliberately does not solve

IntegrationEngine is not a general-purpose HTTP client. It does not handle streaming responses, multipart uploads, retry logic, circuit breaking, or webhook ingestion. For those needs, Saloon or a custom HttpClient adapter is the correct tool. IntegrationEngine is scoped to the request-response call pattern where the caller knows the operation, provides typed input, and expects a typed output.

---

## Requirements

- PHP 8.2+
- Symfony 7.x or 8.x

---

## Installation

```bash
composer require carlosgude/integration-engine
```

If Symfony Flex does not auto-register the bundle, add it manually to
`config/bundles.php`:

```php
return [
    IntegrationEngine\Bundle\IntegrationEngineBundle::class => ['all' => true],
];
```

---

## Quick start

### 1. Generate your first integration

No config file needed beforehand — the command creates everything:

```bash
php bin/console make:integration DummyRestApi GetEmployees
```

The command asks:

1. **Base URL** (first run only): `https://dummy.restapiexample.com`
2. **Path**: `/api/v1/employees`
3. **Method**: `GET`

Generated files:

```
config/packages/integration_engine.yaml
src/Infrastructure/Integrations/DummyRestApi/
    DummyRestApiIntegration.php
    DummyRestApi.yaml
    GetEmployees/
        Request/GetEmployeesAction.php
        Response/GetEmployeesMapper.php
        Response/GetEmployeesResponse.php
```

### 2. Use it from a service

```php
use IntegrationEngine\Core\Contract\DefaultActionContext;

final class EmployeeService
{
    public function __construct(
        private readonly IntegrationRegistry $registry,
    ) {}

    public function getEmployee(int $id): array
    {
        return $this->registry
            ->get(DummyRestApiIntegration::NAME)
            ->send(
                actionName: GetEmployeeAction::getName(),
                context: DefaultActionContext::create(['id' => $id]),
            )
            ->toArray();
    }
}
```

---

## Usage patterns

### Simple GET

```php
->send(GetUsersAction::getName())
```

### With body (POST / PUT)

```php
->send(
    actionName: CreateUserAction::getName(),
    body: CreateUserBody::create(['name' => 'Rick']),
)
```

### With context — built-in (most cases)

Use `DefaultActionContext` for simple path parameter resolution:

```php
use IntegrationEngine\Core\Contract\DefaultActionContext;

->send(
    actionName: GetUserAction::getName(),
    context: DefaultActionContext::create(['id' => 1]),
)
```

### With context — custom class

For contexts with validation, domain semantics, or complex resolution,
implement `ActionContextInterface` directly:

```php
->send(
    actionName: GetOrderAction::getName(),
    context: GetOrderContext::create(['id' => $id, 'warehouse' => $warehouseId]),
)
```

### Mixed (most common)

```php
->send(
    actionName: UpdateUserAction::getName(),
    context: DefaultActionContext::create(['id' => 1]),
    body: UpdateUserBody::create([...]),
)
```

---

## Configuration reference

### Bundle config (`config/packages/integration_engine.yaml`)

```yaml
integration_engine:
  integrations:
    my_api:
      base_url: '%env(MY_API_BASE_URL)%'
      config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/MyApi/MyApi.yaml'
      headers:
        X-Api-Version: '2'
      cache_service: ~     # defaults to InMemoryCacheAdapter — replace in production
      client_service: ~    # custom ClientInterface service ID
```

> **Warning**: The default `InMemoryCacheAdapter` is process-scoped and does not
> persist between requests under PHP-FPM. Configure a `cache_service` backed by
> Redis or APCu for dynamic auth in production.

### Action config (`MyApi.yaml`)

Each action must declare its fully qualified class name:

```yaml
GetUsers:
  action: App\Infrastructure\Integrations\MyApi\GetUsers\Request\GetUsersAction
  method: GET
  path: /users

GetUser:
  action: App\Infrastructure\Integrations\MyApi\GetUser\Request\GetUserAction
  method: GET
  path: /users/{id}
```

The `make:integration` command fills the `action` field automatically.

---

## The bundle proposes, it does not impose

IntegrationEngine defines contracts. What you build on top is entirely yours.

The most powerful pattern it enables without knowing about it is the
**integration base class**: if a group of actions shares auth, a path prefix,
or common configuration, extract it into an abstract class:

```php
abstract class StripeAction extends AbstractAction
{
    public static function create(string $method, string $path, ?ActionBodyInterface $body = null): static
    {
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

Each action then extends the base and only declares what makes it unique:

```php
final class CreateChargeAction extends StripeAction
{
    public static function getName(): string { return 'CreateCharge'; }
    public static function hasResponse(): bool { return true; }
    public static function mapper(): string { return CreateChargeMapper::class; }
}
```

The bundle sees `AbstractAction`. Your domain sees `StripeAction`. Three
levels of design — bundle contract, integration base, operation — with zero
coupling between them.

Full details in [DOCUMENTATION.md — Section 15](./DOCUMENTATION.md#15-the-bundle-proposes-it-does-not-impose).

---

## Further reading

Architecture, authorization, headers, error reference, extensibility and recommended
patterns are covered in the full documentation:

**[→ DOCUMENTATION.md](./DOCUMENTATION.md)**

---

## Demo project

A working Symfony application demonstrating the bundle against the public
[Dummy REST API](https://dummy.restapiexample.com):

**[github.com/CarlosGude/integrationEngine-use-example](https://github.com/CarlosGude/integrationEngine-use-example)**

Clone it, run `composer install` and `symfony server:start` — no database,
no environment variables required.