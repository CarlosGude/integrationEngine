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

```bash
php bin/console make:integration DummyRestApi GetEmployees
```

The command asks three questions on first run:

1. **Base URL**: `https://dummy.restapiexample.com`
2. **Path for the action**: `/api/v1/employees`
3. **HTTP method**: `GET`

It generates everything — config, classes, and YAML:

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

The same command adds new actions to an existing integration. It detects
what already exists and only generates what is missing:

```bash
php bin/console make:integration DummyRestApi GetEmployee
# > Path: /api/v1/employee/{id}
# > Method: GET
# → creates GetEmployee/ files and appends entry to DummyRestApi.yaml
# → skips DummyRestApiIntegration.php (already exists)
```

For GraphQL integrations, the command skips path and method — they are
always `POST /graphql`:

```bash
php bin/console make:integration GitHubGraphQL GetUser
# > Base URL: https://api.github.com/graphql
# > Client type [rest]: graphql
# > Name of the first action/query: GetUser
# → creates GitHubGraphQL/ with client: graphql in integration_engine.yaml
```

`make:integration` is not just for getting started — it is the command you
run every time you add an operation. See section 3 for the full reference.

### 3. Call it

```php
$registry->get(DummyRestApiIntegration::NAME)->send(
    actionName: GetEmployeeAction::getName(),
    context: DefaultActionContext::create(['id' => 1]),
);
```

That is all the bundle requires. Everything else in this document is optional
depth.

### 4. See it in action

A working Symfony application demonstrating the full stack against the public
[Dummy REST API](https://dummy.restapiexample.com) is available at:

**[github.com/CarlosGude/integrationEngine-use-example](https://github.com/CarlosGude/integrationEngine-use-example)**

It is the recommended starting point before reading further.

---

## 1. Ideal usage

The bundle generates the integration layer. What you build on top of it
follows a pattern that keeps the external API isolated from your domain.

### The full stack

```
External API
    → Action (declares the request)
        → Mapper (transforms raw response into integration DTO)
            → Integration facade (exposes named methods, hides the engine)
                → Application service (translates DTO into domain object)
                    → Controller / Command / Queue processor / ...
```

Each layer knows only the layer immediately below it. The domain never
imports anything from `IntegrationEngine\` or from your integration classes.

### What you actually need to touch

Most integrations only require three things:

| When | What |
|------|------|
| Always | Action, Mapper, Response DTO |
| Sometimes | Context (dynamic path params), Body (request payload) |
| Rarely | Custom auth, custom cache, custom HTTP client, custom config source |

The scaffolding generates all three always-required files. For the majority
of integrations, that is all you will ever write.

### The integration facade

The scaffolded `DummyRestApiIntegration` class is the facade. It resolves
the engine once and exposes typed methods:

```php
final class DummyRestApiIntegration
{
    public const string NAME = 'dummy_rest_api';

    private IntegrationEngine $engine;

    public function __construct(IntegrationRegistry $registry)
    {
        $this->engine = $registry->get(self::NAME);
    }

    public function getEmployee(int $id): GetEmployeeResponse
    {
        $response = $this->engine->send(
            actionName: GetEmployeeAction::getName(),
            context: DefaultActionContext::create(['id' => $id]),
        );

        \assert($response instanceof GetEmployeeResponse);

        return $response;
    }
}
```

`GetEmployeeResponse` is an integration DTO — it reflects the external API,
not your domain.

### The application service

The translation from integration DTO to domain object happens in an
injectable service. Not in the controller, not in the domain. The service
does not know how its result will be consumed — controller, command, event
listener, queue processor — so it stays decoupled from the delivery
mechanism:

```php
final class EmployeeService
{
    public function __construct(
        private readonly DummyRestApiIntegration $integration,
    ) {}

    public function getEmployee(int $id): Employee
    {
        $dummyEmployee = $this->integration->getEmployee($id);

        // The service translates. Not the domain, not the controller.
        return new Employee(
            id:     $dummyEmployee->id,
            name:   $dummyEmployee->employeeName,
            salary: $dummyEmployee->employeeSalary,
        );
    }
}
```

Any consumer depends only on `EmployeeService` and works exclusively with
domain objects:

```php
final class EmployeeController extends AbstractController
{
    public function __construct(
        private readonly EmployeeService $employeeService,
    ) {}

    #[Route('/employees/{id}')]
    public function show(int $id): JsonResponse
    {
        return $this->json(
            $this->employeeService->getEmployee($id)
        );
    }
}
```

### What not to do

```php
// ❌ Wrong: the domain now depends on an infrastructure DTO
return Employee::fromDummyEmployee($dummyEmployee);
```

If `Employee` knows what a `GetEmployeeResponse` is, the domain has a
dependency on the integration layer. When the external API changes, the
change propagates into the domain. The separation collapses.

### Why this matters

This is the Anti-Corruption Layer pattern applied at the integration boundary.
The bundle enforces the left side: the mapper must produce a
`ResponseInterface`, and it must correspond to the correct action. The right
side — keeping the integration DTO out of the domain — is your
responsibility. It is the most important convention the bundle asks of you.

---

![IntegrationEngine — visión general](./docs/diagrams/01-overview.svg)

## 2. Philosophy

### The bundle proposes, it does not impose

IntegrationEngine defines contracts. What you build on top of them is
entirely up to you.

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

### Design principles

- No magic outside the engine
- Actions are immutable
- Context is explicit and validated at resolution time
- Bodies are typed objects
- Mapping is explicit via mappers
- Headers have a defined precedence: YAML → auth → caller
- The call site is uniform regardless of integration complexity
- The response boundary is an Anti-Corruption Layer: mappers produce
  integration DTOs, never domain objects. Domain transformation happens
  outside the bundle

### Architecture overview

- **Core**: contracts + engine logic. No framework dependencies.
- **Infrastructure**: HTTP, YAML, and cache adapters. Implements Core ports.
- **Bundle**: Symfony wiring. DI, compiler pass, scaffolding command.

### Core execution model

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

### Integration base classes

If a group of actions shares configuration — auth, a path prefix, common
headers — extract it into an abstract class that extends `AbstractAction`:

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

Each concrete action only declares what makes it unique:

```php
final class CreateChargeAction extends StripeAction
{
    public static function getName(): string { return 'CreateCharge'; }
    public static function hasResponse(): bool { return true; }
    public static function mapper(): string { return CreateChargeMapper::class; }
}
```

| Level | Class | Responsibility |
|-------|-------|----------------|
| Bundle | `AbstractAction` | Contract: method, path, auth, mapper |
| Integration | `StripeAction` | Shared config: auth, prefix, defaults |
| Operation | `CreateChargeAction` | Identity: name, response, mapper |

Use one level, two, or all three. The bundle works the same either way.

---

## 3. Scaffolding

```bash
php bin/console make:integration {IntegrationName} {ActionName}
```

### What the command does

The `action` argument is optional. If omitted, the command asks for it interactively.

| Step | REST | GraphQL |
|------|------|---------|
| First run | Asks base URL + client type | Asks base URL + client type |
| First run | Asks first action name | Asks first action name |
| REST only | Asks path and HTTP method | — skipped, always `POST /graphql` |
| Always | Creates `{Name}Integration.php` | Creates `{Name}Integration.php` |
| Always | Creates Action, Mapper, Response | Creates Action, Mapper, Response |
| Always | Appends entry to `{Name}.yaml` | Appends entry to `{Name}.yaml` |

> **Convention**: `DELETE` generates no Mapper or Response — `hasResponse` is set to `false`. `HEAD` and `OPTIONS` are not supported by the scaffolding. GraphQL actions always have `hasResponse: true`.

### Creating integrations manually

If you create an integration class by hand without using the command, you must
override the `NAME` constant:

```php
final class MyApiIntegration implements IntegrationName
{
    public const string NAME = 'my_api'; // must be declared explicitly
}
```

The interface declares `NAME = '__MUST_OVERRIDE__'` as a sentinel value. PHP
does not enforce constant overriding at the language level. If `NAME` is not
declared, the integration will be registered under `'__MUST_OVERRIDE__'` and
will not resolve correctly from the registry.

---

## 4. Directory structure

Every integration follows the same layout. Understanding it once means being
able to navigate any integration without opening a file.

### Generated structure

Running `make:integration DummyRestApi GetEmployee` on a fresh project
produces the following tree:

```
config/
└── packages/
    └── integration_engine.yaml          ← transport config (base_url, cache, headers)

src/Infrastructure/Integrations/
└── DummyRestApi/
    ├── DummyRestApiIntegration.php      ← facade: exposes typed methods, hides the engine
    ├── DummyRestApi.yaml                ← action registry: maps names to classes
    └── GetEmployee/
        ├── Request/
        │   └── GetEmployeeAction.php    ← declares the request (method, path, mapper)
        └── Response/
            ├── GetEmployeeMapper.php    ← transforms raw array → integration DTO
            └── GetEmployeeResponse.php  ← integration DTO (mirrors the external API)
```

Adding a second action (`make:integration DummyRestApi CreateEmployee`) adds
only the new action subtree and appends an entry to `DummyRestApi.yaml`:

```
└── DummyRestApi/
    ├── DummyRestApiIntegration.php
    ├── DummyRestApi.yaml                ← GetEmployee + CreateEmployee entries
    ├── GetEmployee/
    │   ├── Request/GetEmployeeAction.php
    │   └── Response/
    │       ├── GetEmployeeMapper.php
    │       └── GetEmployeeResponse.php
    └── CreateEmployee/
        ├── Request/CreateEmployeeAction.php
        └── Response/
            ├── CreateEmployeeMapper.php
            └── CreateEmployeeResponse.php
```

### Why this layout

Each directory level has one responsibility:

| Level | Directory | Contains |
|---|---|---|
| Integration | `DummyRestApi/` | Everything for one external provider |
| Facade | `DummyRestApiIntegration.php` | Typed public API — one method per action |
| Action | `GetEmployee/` | Everything for one operation |
| Request | `Request/` | The action class — what to send |
| Response | `Response/` | The mapper and the DTO — what to do with the reply |

The `Request/Response` split inside each action is deliberate. It mirrors
the HTTP contract: a request goes out, a response comes back. Both belong to
the same operation but they solve different problems. Keeping them in
separate subdirectories makes the separation explicit and navigable — when
something breaks in the mapping, you go straight to `Response/`. When the
wrong path is being called, you go straight to `Request/`.

### DELETE actions — no response layer

`DELETE` actions set `hasResponse: false`. The scaffold generates no Mapper
or Response because there is nothing to map:

```
└── DeleteEmployee/
    └── Request/
        └── DeleteEmployeeAction.php    ← hasResponse(): false, mapper(): null
```

The engine returns an `EmptyResponse` and the caller discards it.

### GraphQL — same structure, different body

GraphQL actions follow the same layout. The only difference is that the
Request layer also needs a body class implementing `GraphQLBodyInterface`:

```
└── GetUser/
    ├── Request/
    │   ├── GetUserAction.php
    │   └── GetUserBody.php             ← implements GraphQLBodyInterface (query + variables)
    └── Response/
        ├── GetUserMapper.php
        └── GetUserResponse.php
```

The command generates `GetUserAction` with a comment pointing to
`GraphQLBodyInterface`. The body class is not generated automatically because
the query string is implementation-specific — write it once and it does not
change.

### Naming conventions

The scaffold enforces a consistent naming pattern. Following it outside the
scaffold keeps the codebase uniform:

| File | Pattern | Example |
|---|---|---|
| Action | `{ActionName}Action.php` | `GetEmployeeAction.php` |
| Mapper | `{ActionName}Mapper.php` | `GetEmployeeMapper.php` |
| Response | `{ActionName}Response.php` | `GetEmployeeResponse.php` |
| Body | `{ActionName}Body.php` | `CreateEmployeeBody.php` |
| Facade | `{IntegrationName}Integration.php` | `DummyRestApiIntegration.php` |
| YAML registry | `{IntegrationName}.yaml` | `DummyRestApi.yaml` |

The action name used in YAML (the key) and in `getName()` must match exactly.
The engine resolves actions by name at runtime — a mismatch causes an
`ActionNotFoundException`.

---

## 5. YAML configuration

There are two separate files with different responsibilities.

### Bundle configuration (`config/packages/integration_engine.yaml`)

Registers integrations in the Symfony container and configures their
transport layer. Created automatically by `make:integration` on first run.

```yaml
integration_engine:
  integrations:
    my_api:
      base_url: '%env(MY_API_BASE_URL)%'
      config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/MyApi/MyApi.yaml'
      headers:
        X-Api-Version: '2'
      client: rest           # "rest" (default), "graphql", or any registered custom type
      cache_service: ~       # defaults to Psr6CacheAdapter wrapping cache.app — override with a dedicated pool if needed
      client_service: ~      # custom ClientInterface service ID — overrides client
```

Either `base_url` or `client_service` is required per integration.

> **Note**: The default cache adapter wraps Symfony's `cache.app` via PSR-6. In most production
> setups this is sufficient. If you need a dedicated cache pool for dynamic auth tokens — for
> example to control TTL independently — configure `cache_service` with a custom pool id.

> **Warning**: `config_path` is required and validated at **compile time** by the bundle's compiler pass. A missing `config_path` key will throw an `InvalidArgumentException` during container compilation. A path that is declared but points to a non-existent file will be caught at **runtime**, on the first request that touches that integration. Verify all paths after deploy and consider adding a smoke test or health check that exercises each integration.

### Integration configuration (`src/Infrastructure/Integrations/MyApi/MyApi.yaml`)

Declares the operations available for a specific integration. Generated and
updated automatically by `make:integration`.

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

No logic lives in YAML — YAML declares intent; Actions and Mappers implement
behaviour.

---

## 6. Actions

### YAML vs Action — why both exist

The YAML config and the Action class both mention method and path. They serve
different purposes:

- **YAML** is the source of truth at boot time. The engine reads it to know
  which Action class to instantiate and with which method and path.
- **The Action class** is the object in memory at runtime. It carries
  behaviour that YAML cannot express: which mapper to use, whether a response
  is expected, custom path resolution logic, shared auth configuration.

YAML declares intent. The Action implements behaviour. Neither replaces the
other.

### Lifecycle

```
YAML config
    → engine reads method, path, action class, authorization
        → instantiates the Action via Action::create(method, path, ...)
            → passes it to the HTTP client and mapper
```

The developer never instantiates an Action directly. The engine does it.

### What an Action declares

An Action defines the HTTP method, the path template, an optional body, and
optional authorization. Actions are stateless and immutable — all constructor
properties are `readonly`. The engine passes context directly to `getPath()`
at call time. The action never stores execution state. The same instance can
be called with different contexts in successive requests without mutation.

### Generated classes — complete example

`make:integration DummyRestApi GetEmployee` produces three files. Here is
what a complete implementation looks like after filling them in:

```php
// Request/GetEmployeeAction.php
final class GetEmployeeAction extends AbstractAction
{
    public static function getName(): string { return 'GetEmployee'; }
    public static function hasResponse(): bool { return true; }
    public static function mapper(): string { return GetEmployeeMapper::class; }
}
```

```php
// Response/GetEmployeeResponse.php
// Integration DTO — mirrors the external API, not your domain.
final class GetEmployeeResponse implements ResponseInterface
{
    public function __construct(
        public readonly int    $id,
        public readonly string $employeeName,
        public readonly string $employeeSalary,
        public readonly string $employeeAge,
    ) {}

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'employee_name'   => $this->employeeName,
            'employee_salary' => $this->employeeSalary,
            'employee_age'    => $this->employeeAge,
        ];
    }
}
```

```php
// Response/GetEmployeeMapper.php
// Receives the raw array from the server and builds the integration DTO.
// The engine verifies at runtime that this mapper belongs to GetEmployeeAction.
final class GetEmployeeMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return GetEmployeeAction::class;
    }

    protected static function transform(
        AbstractAction $action,
        array $response,
    ): ResponseInterface {
        $data = $response['data'];

        return new GetEmployeeResponse(
            id:             (int) $data['id'],
            employeeName:   (string) $data['employee_name'],
            employeeSalary: (string) $data['employee_salary'],
            employeeAge:    (string) $data['employee_age'],
        );
    }
}
```

## 7. Context system

Context resolves dynamic URL path segments at call time:

```php
/orders/{id}  →  /orders/42
```

### DefaultActionContext

General-purpose implementation that covers the vast majority of cases:

```php
->send(
    actionName: GetOrderAction::getName(),
    context: DefaultActionContext::create(['id' => 42]),
)
```

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

### Path resolution

Missing parameters throw a `RuntimeException` at resolution time, not at
HTTP time. A custom resolver can be provided by overriding
`resolvePathCallback()` in the Action:

```php
protected function resolvePathCallback(): ?callable
{
    return function (string $path, ?ActionContextInterface $context): string {
        return $resolvedPath;
    };
}
```


> **Note**: The default resolver uses `\w+` to match placeholder names (`[a-zA-Z0-9_]`). Parameter names containing hyphens — common in some REST APIs (e.g. `{user-id}`) — will not be resolved and the placeholder will remain literal in the path, causing a silent 404. Use `resolvePathCallback()` to handle these cases:
>
> ```php
> protected function resolvePathCallback(): ?callable
> {
>     return function (string $path, ?ActionContextInterface $context): string {
>         $data = $context?->toArray() ?? [];
>         return preg_replace_callback('/\{([^}]+)\}/', static function (array $m) use ($data, $path): string {
>             if (!array_key_exists($m[1], $data)) {
>                 throw PathResolutionException::missingParameter($m[1], $path);
>             }
>             return (string) $data[$m[1]];
>         }, $path) ?? $path;
>     };
> }
> ```

## 8. Body system

Bodies are explicit objects implementing `ActionBodyInterface`:

```php
final class CreateOrderBody implements ActionBodyInterface
{
    public static function create(array $data): self {}
    public function toArray(): array {}
}
```

Bodies are serialised as JSON and sent for `POST`, `PUT`, and `PATCH` requests.

> **Note**: If a body object is passed to `engine->send()` but the action does not declare a
> `body` class in its YAML config, the engine throws an `InvalidArgumentException`. This prevents
> silently discarding request payloads when the YAML and the call site are out of sync.

### GraphQL bodies

For GraphQL integrations, implement `GraphQLBodyInterface` instead of
`ActionBodyInterface`. It adds two methods: `getQuery()` and `getVariables()`.

```php
final class GetUserBody implements GraphQLBodyInterface
{
    public function __construct(private readonly string $login) {}

    public function getQuery(): string
    {
        // Inline or loaded from a .graphql file
        return file_get_contents(__DIR__ . '/../queries/get_user.graphql');
    }

    public function getVariables(): array
    {
        return ['login' => $this->login];
    }

    public function toArray(): array
    {
        return ['query' => $this->getQuery(), 'variables' => $this->getVariables()];
    }

    public static function create(array $data): self
    {
        return new self((string) $data['login']);
    }
}
```

The `GraphQLClientAdapter` serialises this as `{ "query": "...", "variables": {...} }`
and sends it as `POST` to the configured endpoint. The mapper receives only
the `data` key of the GraphQL response — errors are detected automatically
and thrown as `RequestResponseException`.

## 9. Authorization system

### Static authorization

| Type      | Header produced                     |
|-----------|-------------------------------------|
| `bearer`  | `Authorization: Bearer {token}` (prefix configurable via `prefix:`) |
| `basic`   | `Authorization: Basic {b64}`        |
| `api_key` | `{header}: {token}` (custom header) |

### Dynamic authorization — complete example

For APIs that require a pre-flight token request (OAuth, session tokens,
API key exchanges), the auth action is a regular action like any other.
The engine executes it, extracts the token, caches it, and substitutes
a static auth transparently before the actual request.

**Step 1 — Declare the token action and its response:**

```php
// FetchTokenAction.php
final class FetchTokenAction extends AbstractAction
{
    public static function getName(): string { return 'FetchToken'; }
    public static function hasResponse(): bool { return true; }
    public static function mapper(): string { return FetchTokenMapper::class; }
}

// FetchTokenResponse.php
final class FetchTokenResponse implements ResponseInterface
{
    public function __construct(
        public readonly string $accessToken,
    ) {}

    public function toArray(): array
    {
        return ['access_token' => $this->accessToken];
    }
}

// FetchTokenMapper.php
final class FetchTokenMapper extends AbstractMapper
{
    public static function getAction(): string { return FetchTokenAction::class; }

    protected static function transform(
        AbstractAction $action,
        array $response,
    ): ResponseInterface {
        return new FetchTokenResponse(
            accessToken: (string) $response['access_token'],
        );
    }
}
```

**Step 2 — Register both actions in the integration YAML:**

```yaml
FetchToken:
  action: App\Infrastructure\Integrations\MyApi\FetchToken\Request\FetchTokenAction
  method: POST
  path: /oauth/token

GetOrders:
  action: App\Infrastructure\Integrations\MyApi\GetOrders\Request\GetOrdersAction
  method: GET
  path: /orders
  authorization:
    type: dynamic
    action: FetchToken
    token_field: access_token
    ttl: 3600
    # prefix: Token   # optional — defaults to "Bearer". Use for non-Bearer Authorization schemes (e.g. "Token", "Digest")
```

**What happens at runtime:**

1. `engine->send('GetOrders')` is called.
2. The engine detects `authorization.type: dynamic`.
3. It checks the cache for `integration_engine.token.{integrationName}.FetchToken`.
4. Cache miss → executes `FetchTokenAction`, maps the response via
   `FetchTokenMapper`, extracts `access_token`.
5. Stores the token in cache for 3600 seconds.
6. Reconstructs `GetOrdersAction` with a `StaticAuthorizationConfig`
   carrying the token as a Bearer header.
7. Executes the actual request.

On subsequent calls within the TTL, step 4 is skipped entirely. The
integration author writes no caching logic.

> **Limitation**: Dynamic authorization only supports `bearer` (for `Authorization` header) and
> `api_key` (for any other header name). The `basic` auth type — which requires a username and
> password rather than a single token — is not compatible with the dynamic auth flow, since the
> engine expects a single scalar value from `token_field`. For APIs using HTTP Basic auth with
> dynamic credentials, use a custom `ClientInterface` instead.

## 10. Headers system

Headers are resolved in three layers. Each layer overrides the previous:

```
YAML defaults  →  Auth headers  →  Caller headers
```

**Layer 1 — YAML defaults**: fixed headers for the integration, declared in
`integration_engine.yaml`. Use for API versioning, client identification.

**Layer 2 — Auth headers**: resolved from the Action's `AuthorizationConfig`.
Always override YAML defaults.

**Layer 3 — Caller headers**: per-request headers passed at call time.
Implement `RequestHeadersInterface`:

```php
final class CorrelationHeaders implements RequestHeadersInterface
{
    public function __construct(private readonly string $requestId) {}

    public function toArray(): array
    {
        return ['X-Correlation-ID' => $this->requestId];
    }
}
```

## 11. Engine API

```php
send(
    string $actionName,
    ?ActionContextInterface $context = null,
    ?ActionBodyInterface $body = null,
    ?RequestHeadersInterface $headers = null,
): ResponseInterface
```

**Flow**: load action → resolve context → resolve auth → execute HTTP →
map response → return `ResponseInterface`.

Use `assert()` to narrow the return type for PHPStan without runtime cost:

```php
$response = $this->engine->send(...);
\assert($response instanceof GetEmployeeResponse);
return $response;
```

## 12. The response boundary

`ResponseInterface` requires only `toArray()`. This is intentional — it is
the point where the bundle's responsibility ends and yours begins. See
section 1 for the full usage pattern.

`toArray()` exists for one internal reason: the engine uses it to extract
tokens in dynamic auth flows. It is not the public API of your integration
DTO. Expose typed fields on the concrete class; the domain consumes those
fields and builds its own objects.

The Mapper is the structural guarantee: it receives a raw array, must return
a `ResponseInterface`, and the engine verifies at runtime that the mapper
corresponds to the correct action. What the response object contains beyond
that is entirely up to you.

## 13. Extensibility

Every infrastructure component is replaceable:

| Contract            | Default implementation         | Override via                    |
|---------------------|--------------------------------|---------------------------------|
| `ClientInterface`   | `SymfonyHttpClientAdapter`     | `client_service` or `client`    |
| `CachePort`         | `Psr6CacheAdapter` (wraps `cache.app`)  | `cache_service`                 |
| `ConfigPort`        | `YamlConfigAdapter`            | custom CompilerPass             |

### Custom HTTP adapters

Implement `ClientAdapterInterface` to create a new adapter type (e.g. SOAP,
XML-RPC, or a custom protocol). The interface extends `ClientInterface` and
adds one static method:

```php
final readonly class SoapClientAdapter implements ClientAdapterInterface
{
    public static function getClientType(): string  { return 'soap'; }
    public static function requiresPath(): bool     { return false; }
    public static function requiresMethod(): bool   { return false; }

    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
        // your implementation
    }
}
```

Register it in your project's `services.yaml`:

```yaml
App\Infrastructure\Http\SoapClientAdapter:
  tags:
    - { name: integration_engine.client_adapter }
```

Then use it in your integration config:

```yaml
integration_engine:
  integrations:
    my_soap_api:
      base_url: 'https://api.example.com/soap'
      client: soap
```

Project adapters registered after bundle built-ins will override them for the
same type. Registering an adapter with `client: rest` replaces
`SymfonyHttpClientAdapter` for that integration.

## 14. Bundle internals

This section is for developers who want to extend or fork the bundle itself —
not just use it. It describes how the three layers are wired together and
where each extension point lives.

### Layer map

```
Bundle/
├── IntegrationEngineBundle.php              ← registers the compiler pass
├── DependencyInjection/
│   ├── IntegrationEngineExtension.php       ← loads services.yaml, exposes config as parameters
│   ├── Configuration.php                    ← defines and validates the config tree
│   └── Compiler/
│       └── IntegrationCompilerPass.php      ← builds one IntegrationEngine per integration
├── Exception/
│   └── IntegrationConfigurationException.php ← compile-time config errors
├── Command/
│   └── MakeIntegrationCommand.php           ← make:integration scaffolding
└── Generator/
    ├── IntegrationContext.php               ← value object: all data for one generation run
    ├── IntegrationFileGenerator.php         ← decides which files to write and where
    └── TemplateRenderer.php                 ← produces the PHP/YAML content strings

Core/
├── IntegrationEngine.php                    ← orchestrates one send() call end-to-end
├── Contract/
│   ├── AbstractAction.php                   ← base for all actions
│   ├── AbstractMapper.php                   ← base for all mappers
│   ├── ActionBodyInterface.php
│   ├── ActionContextInterface.php
│   ├── AuthorizationConfig.php              ← factory: dispatches to Static or Dynamic
│   ├── StaticAuthorizationConfig.php
│   ├── DynamicAuthorizationConfig.php
│   ├── ClientInterface.php                  ← port: what the engine calls
│   ├── ClientAdapterInterface.php           ← extends ClientInterface + type metadata
│   ├── RequestHeadersInterface.php
│   ├── ResponseInterface.php
│   └── GraphQLBodyInterface.php
├── Port/
│   ├── CachePort.php                        ← port: get / set
│   └── ConfigPort.php                       ← port: getAction()
├── Registry/
│   ├── IntegrationRegistry.php              ← runtime map of name → IntegrationEngine
│   └── IntegrationName.php                  ← marker interface for NAME constants
├── Response/
│   └── EmptyResponse.php                    ← returned when hasResponse() is false
└── Exception/                               ← one exception class per failure mode

Infrastructure/
├── Adapter/
│   └── YamlConfigAdapter.php                ← ConfigPort implementation
├── Cache/
│   └── Psr6CacheAdapter.php                 ← CachePort implementation
└── Http/
    ├── ClientAdapterResolver.php            ← type string → adapter class map
    ├── SymfonyHttpClientAdapter.php          ← ClientAdapterInterface: REST
    └── GraphQLClientAdapter.php             ← ClientAdapterInterface: GraphQL
```

### Boot sequence

Understanding the two-phase boot is essential before modifying anything.

**Phase 1 — Container compilation** (`IntegrationCompilerPass::process()`):

```
IntegrationEngineExtension::load()
  → reads integration_engine.yaml
  → validates config tree (Configuration.php)
  → stores integrations as container parameter: integration_engine.integrations

IntegrationCompilerPass::process()
  → reads the parameter
  → scans services tagged integration_engine.client_adapter
      → builds type → class map (later registrations override earlier ones)
      → calls ClientAdapterResolver::register() for each
  → for each integration:
      → creates Definition for YamlConfigAdapter(config_path)   [integration_engine.config.{name}]
      → creates Definition for the HTTP adapter(http_client, base_url, headers)  [integration_engine.http_client.{name}]
        (or uses client_service reference directly)
      → creates Definition for IntegrationEngine(config, client, cache, name)   [integration_engine.integration.{name}]
      → calls IntegrationRegistry::register(name, engine)
```

All service IDs follow the pattern `integration_engine.{type}.{name}`.
They are not public by default — they are wired internally via `Reference`.

**Phase 2 — Runtime** (`IntegrationEngine::send()`):

```
send(actionName, context, body, headers)
  → ConfigPort::getAction(name, body)          ← reads YAML, instantiates Action
  → applyAuthorization(action)
      → if DynamicAuthorizationConfig:
          → cache lookup (CachePort::get)
          → cache miss: send auth action, map response, extract token, cache it
          → reconstruct action with StaticAuthorizationConfig
  → ClientInterface::send(action, context, headers)
  → if !action::hasResponse(): return EmptyResponse
  → applyMapper(action, rawResponse)
      → guard: mapper::getAction() === action::class
      → mapper::map(action, rawResponse)
      → return ResponseInterface
```

### Extension points

There are four ways to extend the bundle, in increasing order of invasiveness:

**1. New client adapter type** (most common)

Implement `ClientAdapterInterface`, tag it, use it via `client:` in YAML.
The compiler pass discovers it automatically. See section 13.

**2. Replace an infrastructure component per integration**

Every integration can use a different `ClientInterface`, `CachePort`, or
`ConfigPort` implementation. None of them need to be registered globally —
just pass the service ID via `client_service` or `cache_service`.

To replace `ConfigPort` entirely (e.g. to load actions from a database
instead of YAML), implement `ConfigPort` and wire it manually in a custom
compiler pass:

```php
// src/Infrastructure/Integrations/MyApi/DatabaseConfigAdapter.php
final class DatabaseConfigAdapter implements ConfigPort
{
    public function __construct(private readonly Connection $db) {}

    public function getAction(string $name, ?ActionBodyInterface $body): AbstractAction
    {
        // load from DB, instantiate action
    }
}
```

```php
// src/Bundle/Compiler/MyApiConfigPass.php
final class MyApiConfigPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // override the definition created by IntegrationCompilerPass
        $container->getDefinition('integration_engine.config.my_api')
            ->setClass(DatabaseConfigAdapter::class)
            ->setArguments([new Reference('doctrine.dbal.default_connection')]);
    }
}
```

Register it after `IntegrationCompilerPass` so it runs later and wins:

```php
// src/Kernel.php or a bundle's build()
$container->addCompilerPass(new MyApiConfigPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -10);
```

**3. Extend the bundle configuration**

To add new config keys to `integration_engine.yaml`, extend `Configuration`
and `IntegrationEngineExtension`. The cleanest approach if you are building
a library on top of this bundle is to create your own bundle that declares
a `PrependExtensionInterface` and injects config into `integration_engine`:

```php
final class MyLibraryBundle extends Bundle implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('integration_engine', [
            'integrations' => [
                'my_api' => [
                    'base_url'    => '%env(MY_API_URL)%',
                    'config_path' => __DIR__.'/Resources/my_api.yaml',
                ],
            ],
        ]);
    }
}
```

**4. Fork the compiler pass** (most invasive)

If you need to change how `IntegrationEngine` instances are constructed —
for example to inject additional collaborators — copy `IntegrationCompilerPass`
and register yours instead. The service ID conventions
(`integration_engine.config.{name}`, `integration_engine.http_client.{name}`,
`integration_engine.integration.{name}`) are stable and can be relied upon.

### Service ID reference

| Service ID | Class | Created by |
|---|---|---|
| `integration_engine.registry` | `IntegrationRegistry` | `services.yaml` |
| `integration_engine.cache.default` | `Psr6CacheAdapter` | `services.yaml` |
| `integration_engine.resolver` | `ClientAdapterResolver` | `services.yaml` |
| `integration_engine.config.{name}` | `YamlConfigAdapter` | Compiler pass |
| `integration_engine.http_client.{name}` | adapter class | Compiler pass |
| `integration_engine.integration.{name}` | `IntegrationEngine` | Compiler pass |

### DI tag reference

| Tag | Used by | Effect |
|---|---|---|
| `integration_engine.client_adapter` | any `ClientAdapterInterface` | Registers the adapter with `ClientAdapterResolver` at compile time |

### What not to override

`IntegrationEngine` itself is `final readonly`. It is not designed to be
subclassed — its behaviour is fixed by its collaborators. Swap the
collaborators, not the engine.

`AbstractAction::create()` uses `new static()` deliberately. Subclasses
inherit it without needing to override it. Do not change the constructor
signature — the compiler pass constructs actions via `create()`, and so does
any code that reconstructs an action during dynamic auth.

`AbstractMapper::map()` is `final`. The guard it performs — verifying that
the mapper belongs to the action — is a correctness invariant. Override
`transform()` instead.

---

## 15. Error reference

| Exception                        | When                                                                              | Action                                                          |
|----------------------------------|-----------------------------------------------------------------------------------|-----------------------------------------------------------------|
| `ActionNotFoundException`        | `send()` called with an action name not in the YAML config                        | Verify the action name matches the YAML key exactly             |
| `NotMappedActionException`       | `mapper()` returns `null` but `hasResponse()` is `true`                          | Declare a mapper class or set `hasResponse: false`              |
| `MapperActionMismatchException`  | The mapper's `getAction()` does not match the action being executed               | Ensure each mapper declares the correct Action class            |
| `RequestResponseException`       | HTTP 4xx/5xx or network error                                                     | Inspect `$e->statusCode` and `$e->context`                    |
| `RuntimeException`               | A path parameter is missing from the context                                      | Ensure all `{param}` placeholders are covered                   |
| `RuntimeException`               | Dynamic auth response does not contain the expected `token_field`                 | Verify the auth action response structure matches the config    |
| `InvalidArgumentException`       | Integration YAML file is empty or its content is not a valid YAML map             | Check the YAML file is not empty and has the correct structure  |
| `InvalidArgumentException`       | Action class declared in YAML does not exist or does not extend `AbstractAction`  | Verify the FQCN in the `action` field and run `composer dump-autoload` |
| `InvalidArgumentException`       | `client` value in YAML is not registered (e.g. `client: soap` with no adapter)  | Register the adapter with the `integration_engine.client_adapter` tag  |
| `RequestResponseException`       | GraphQL response contains `errors` (HTTP 200 with error payload)                  | Inspect `$e->context` for the GraphQL error message                    |