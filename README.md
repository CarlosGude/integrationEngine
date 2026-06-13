# IntegrationEngine

External integrations tend to rot in Symfony projects.

Every API becomes a different shape, a different structure, a different way of thinking.
After a few months, you no longer have integrations. You have a zoo.

IntegrationEngine forces every integration to look the same.

---

## Core idea

An integration is not a client.

It is a collection of predictable endpoints.

Every endpoint has exactly two responsibilities:

- Request (what goes in)
- Response (what comes out)

Nothing else is allowed to sprawl.

---

## What this solves

IntegrationEngine removes:

- Inconsistent API clients across services
- Ad-hoc HTTP logic scattered in services
- Repeated mapping boilerplate per endpoint
- "How does this API work again?" moments
- Integration archaeology after months

---

## Quick usage

```php
$employee = $dummyRestApi->getEmployee(123);
```

No HTTP clients. No request builders. No mappers. Just integrations.

---

## Installation

```bash
composer require carlosgude/integration-engine
```

Requires PHP 8.2+ and Symfony 7.0+. The bundle registers itself automatically via Symfony Flex.

---

## Structure

Each integration follows the same predictable directory layout:

```
src/Infrastructure/Integrations/{Name}/
├── {Name}Integration.php          ← facade + NAME constant
├── {Name}.yaml                    ← action map (path, method, class)
└── {ActionName}/
    ├── Request/
    │   └── {ActionName}Action.php
    └── Response/
        ├── {ActionName}Response.php
        └── {ActionName}Mapper.php
```

Shared DTOs used by multiple actions go in a `Dto/` directory at the integration root.

If you know one integration, you know them all.

---

## Configuration

Register each integration in `config/packages/integration_engine.yaml`:

```yaml
integration_engine:
    integrations:
        my_api:
            base_url: 'https://api.example.com'
            config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/MyApi/MyApi.yaml'
            headers:              # optional — sent with every request
                X-Api-Version: '2'
            cache_service: ~      # optional — defaults to cache.app
            client_service: ~     # optional — fully custom ClientInterface
```

The action map YAML maps each action name to its class, method, and path:

```yaml
GetEmployee:
    action: App\Infrastructure\Integrations\MyApi\GetEmployee\Request\GetEmployeeAction
    method: GET
    path: /employees/{id}

CreateEmployee:
    action: App\Infrastructure\Integrations\MyApi\CreateEmployee\Request\CreateEmployeeAction
    method: POST
    path: /employees
```

---

## Sending a request

The engine is accessed via `IntegrationRegistry`. Always wrap registry calls in an
integration facade — never call the registry directly from a controller or service:

```php
use IntegrationEngine\Core\Batch\EngineRequest;
use IntegrationEngine\Core\Contract\DefaultActionContext;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Core\Registry\IntegrationName;
use IntegrationEngine\Core\Registry\IntegrationRegistry;

// 1. Facade (infrastructure layer)
final class MyApiIntegration implements IntegrationName
{
    public const string NAME = 'my_api';

    private IntegrationEngine $engine;

    public function __construct(IntegrationRegistry $registry)
    {
        $this->engine = $registry->get(self::NAME);
    }

    // Single request
    public function getEmployee(int $id): GetEmployeeResponse
    {
        $response = $this->engine->send(
            actionName: GetEmployeeAction::getName(),
            context: DefaultActionContext::create(['id' => $id]),
        );
        \assert($response instanceof GetEmployeeResponse);
        return $response;
    }

    // Parallel fan-out — N requests at once, results keyed like the input
    public function getManyEmployees(array $ids): array
    {
        $requests = [];
        foreach ($ids as $id) {
            $requests[$id] = EngineRequest::create(
                GetEmployeeAction::getName(),
                DefaultActionContext::create(['id' => $id]),
            );
        }

        $results = $this->engine->sendMany($requests); // BatchResultCollection

        if ($results->hasFailures()) {
            throw array_values($results->errors())[0];
        }

        return array_map(fn($dto) => Employee::fromDto($dto), $results->responses());
    }
}

// 2. Application service — translates infrastructure DTOs to domain objects
final class EmployeeService
{
    public function __construct(private MyApiIntegration $integration) {}

    public function find(int $id): Employee // domain object
    {
        $dto = $this->integration->getEmployee($id);
        return new Employee(id: $dto->employee->id, name: $dto->employee->name);
    }
}
```

`sendMany()` returns a `BatchResultCollection` — one result per key, successes and
failures independent. Real concurrency requires the client to implement
`BatchClientInterface`: the default `rest` client does, `graphql` does not. For GraphQL
or SOAP with real concurrency, use `client_service:` and implement `BatchClientInterface`
yourself. See [DOCUMENTATION.md](DOCUMENTATION.md) → *Batch / Parallel Requests* for
the full API, failure-handling patterns, and concurrency details.

---

## Path parameters and query strings

Path segment parameters (`{id}`) are resolved automatically from context:

```yaml
GetEmployee:
    path: /employees/{id}
```

```php
DefaultActionContext::create(['id' => 42]) // → /employees/42
```

For **optional** query string filters, implement `PathResolvableContextInterface` in a
custom context — path logic lives in the context, the action stays declarative:

```php
use IntegrationEngine\Core\Contract\PathResolvableContextInterface;

final readonly class FilterEmployeesContext implements PathResolvableContextInterface
{
    private function __construct(private array $filters) {}

    public static function create(array $data): self { return new self($data); }
    public function toArray(): array { return $this->filters; }

    public function resolvePath(string $path): ?string
    {
        $allowed = ['status', 'department', 'page'];
        $params  = array_filter(
            array_intersect_key($this->filters, array_flip($allowed)),
            static fn(mixed $v): bool => '' !== (string) $v,
        );
        // null → fall back to the default {placeholder} resolver
        return empty($params) ? null : $path . '?' . http_build_query($params);
    }
}
```

For **required** query string params, declare them as placeholders directly in the YAML path:

```yaml
FilterByStatus:
    path: /employees?status={status}  # throws if 'status' is missing from context
```

---

## Authorization

### Static (API key, bearer token, basic auth)

Declare in the action entry in `{Name}.yaml`:

```yaml
GetOrders:
    action: App\...\GetOrdersAction
    method: GET
    path: /orders
    authorization:
        type: bearer
        token: '%env(MY_API_TOKEN)%'
```

Supported types: `bearer`, `basic` (`username` + `password`), `api_key` (`header` + `token`,
optional `prefix`).

### Dynamic (OAuth 2.0, session tokens)

The engine calls a token action automatically, caches the result, and injects it as static
auth on all protected actions. No manual token management needed:

```yaml
FetchToken:
    action: App\...\FetchTokenAction
    method: POST
    path: /oauth/token

GetOrders:
    action: App\...\GetOrdersAction
    method: GET
    path: /orders
    authorization:
        type: dynamic
        action: FetchToken      # calls this action to obtain the token
        token_field: access_token
        ttl: 3600
        header: Authorization   # optional — header carrying the token
        prefix: Bearer          # optional — defaults to Bearer for Authorization, none for custom headers
```

The token action is a regular action and requires its own `Action`, `Mapper`, and `Response`.
The response must expose the token field via `toArray()`.

If a cached token is rejected with HTTP 401 before its TTL expires (revoked or expired
server-side), the engine evicts it from the cache and retries the request **once** with a
freshly fetched token. No manual token invalidation needed.

> **Cache scope.** The default cache backend is `cache.app`, which is process-local under
> PHP-FPM. Each worker fetches its own token on first warm-up. For APIs with strict
> rate limits on the token endpoint, configure `cache_service` with a shared Redis pool.

---

## HTTP adapters

Two adapters are included:

| Type | Key | Use case |
|---|---|---|
| `SymfonyHttpClientAdapter` | `rest` | Standard REST APIs |
| `GraphQLClientAdapter` | `graphql` | GraphQL endpoints |

For GraphQL actions, the body must implement `GraphQLBodyInterface`:

```php
use IntegrationEngine\Core\Contract\GraphQLBodyInterface;

final class GetUserBody implements GraphQLBodyInterface
{
    public function getQuery(): string  { return 'query { user(id: $id) { name } }'; }
    public function getVariables(): array { return ['id' => $this->id]; }
    public function toArray(): array    { return ['query' => $this->getQuery(), 'variables' => $this->getVariables()]; }
    public static function create(array $data): self { return new self((int) $data['id']); }
}
```

### Custom adapters

Implement `ClientAdapterInterface` and tag the service — the bundle discovers it automatically:

```php
use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\ClientAdapterInterface;

final class SoapClientAdapter implements ClientAdapterInterface
{
    public static function getClientType(): string { return 'soap'; }
    public static function requiresPath(): bool    { return false; }
    public static function requiresMethod(): bool  { return false; }
    public function send(AbstractAction $action, ...): array { ... }
}
```

```yaml
# services.yaml
App\Infrastructure\Http\SoapClientAdapter:
    tags:
        - { name: integration_engine.client_adapter }
```

Project adapters override bundle built-ins when registered with the same `getClientType()`.

---

## Scaffolding

Generate a full integration skeleton with the built-in command:

```bash
# New integration + first action
php bin/console make:integration MyApi GetEmployee

# Add an action to an existing integration
php bin/console make:integration MyApi CreateEmployee
```

The command is interactive — it asks for base URL, client type, HTTP method, and path.
It generates the `Action`, `Mapper`, `Response`, and updates the YAML action map.

---

## Further reading

- [`ARCHITECTURE.md`](./ARCHITECTURE.md) — design decisions: why actions are stateless,
  the mapper invariant, cache behaviour under PHP-FPM, and the DTO/domain boundary.
- [`TESTING.md`](./TESTING.md) — test philosophy, suite structure, and what each test protects.
- [`CONTRIBUTING.md`](./CONTRIBUTING.md) — setup, code quality tools, and how to run the test suite.

---

## When NOT to use it

- You only have 1–2 simple API calls
- You need full low-level HTTP control everywhere
- You don't want enforced structure in your codebase
