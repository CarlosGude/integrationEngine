# IntegrationEngine Bundle

A Symfony bundle for centralising external API integrations behind a consistent, hexagonal architecture.

> **Full documentation** → [DOCUMENTATION.md](./DOCUMENTATION.md)

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

The same command adds new actions to an existing integration — it detects
what already exists and only generates what is missing.

### 2. The correct usage pattern

The integration facade wraps the engine. An application service translates
the integration DTO to a domain object. The controller depends only on the
service:

```php
// 1. Fill in the generated facade
public function getEmployee(int $id): GetEmployeeResponse
{
    $response = $this->engine->send(
        actionName: GetEmployeeAction::getName(),
        context: DefaultActionContext::create(['id' => $id]),
    );

    \assert($response instanceof GetEmployeeResponse);
    return $response;
}

// 2. Translate to domain in an application service
final class EmployeeService
{
    public function __construct(
        private readonly DummyRestApiIntegration $integration,
    ) {}

    public function getEmployee(int $id): Employee
    {
        $dto = $this->integration->getEmployee($id);

        return new Employee(
            id:     $dto->id,
            name:   $dto->employeeName,
            salary: $dto->employeeSalary,
        );
    }
}
```

The integration DTO never reaches the domain. See [section 1 of DOCUMENTATION.md](./DOCUMENTATION.md#1-ideal-usage) for the full pattern and the Anti-Corruption Layer explanation.

### 3. Demo project

A working Symfony application demonstrating the full stack:

**[github.com/CarlosGude/integrationEngine-use-example](https://github.com/CarlosGude/integrationEngine-use-example)**

Clone it, run `composer install` and `symfony server:start` — no database,
no environment variables required.

---

## Usage patterns

### Simple GET

```php
$this->engine->send(GetOrdersAction::getName())
```

### With context — path parameters

```php
use IntegrationEngine\Core\Contract\DefaultActionContext;

$this->engine->send(
    actionName: GetOrderAction::getName(),
    context: DefaultActionContext::create(['id' => $id]),
)
```

### With body (POST / PUT)

```php
$this->engine->send(
    actionName: CreateOrderAction::getName(),
    body: CreateOrderBody::create(['reference' => 'ORD-001']),
)
```

### GraphQL

```php
$this->engine->send(
    actionName: GetUserAction::getName(),
    body: GetUserBody::create(['login' => 'CarlosGude']),
)
```

`GetUserBody` implements `GraphQLBodyInterface` — declares `getQuery()` and
`getVariables()`. The adapter handles the rest.

### With context and body

```php
$this->engine->send(
    actionName: UpdateOrderAction::getName(),
    context: DefaultActionContext::create(['id' => $id]),
    body: UpdateOrderBody::create([...]),
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

```yaml
GetOrders:
  action: App\Infrastructure\Integrations\MyApi\GetOrders\Request\GetOrdersAction
  method: GET
  path: /orders

GetOrder:
  action: App\Infrastructure\Integrations\MyApi\GetOrder\Request\GetOrderAction
  method: GET
  path: /orders/{id}
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
    public static function getName(): string   { return 'CreateCharge'; }
    public static function hasResponse(): bool { return true; }
    public static function mapper(): string    { return CreateChargeMapper::class; }
}
```

Full details in [DOCUMENTATION.md — Section 2](./DOCUMENTATION.md#2-philosophy).

---

## What this bundle deliberately does not solve

IntegrationEngine is not a general-purpose HTTP client. It does not handle
streaming responses, multipart uploads, retry logic, circuit breaking, or
webhook ingestion. For those needs, Saloon or a custom `ClientInterface`
adapter is the correct tool. IntegrationEngine is scoped to the
request-response call pattern where the caller knows the operation, provides
typed input, and expects a typed output.

---

## Further reading

Architecture, authorization, headers, error reference, extensibility and
recommended patterns:

**[→ DOCUMENTATION.md](./DOCUMENTATION.md)**

Test suite structure, fakes, and the rationale behind each test:

**[→ TESTING.md](./TESTING.md)**