# IntegrationEngine Bundle

Most Symfony applications start with a few simple HTTP calls:

```php
$response = $httpClient->request('GET', '/users');
```

That works well. The problem appears when the application grows and external APIs become part of the business process:

- Multiple providers with different authentication mechanisms
- Repeated request construction scattered across services
- DTO mapping duplicated throughout the codebase
- Controllers and services depending on provider-specific structures

IntegrationEngine provides a consistent, hexagonal structure for those integrations while remaining framework-native and fully extensible.

---

## Why not just Symfony HttpClient?

Symfony HttpClient sends HTTP requests. IntegrationEngine structures integrations.

**Use HttpClient when:**
- You need a small number of simple requests
- The API is not part of your core business flow

**Use IntegrationEngine when:**
- The integration becomes part of your application architecture
- Multiple actions belong to the same provider
- You want typed requests and responses
- You want a clear Anti-Corruption Layer between external APIs and your domain

IntegrationEngine uses Symfony HttpClient internally and can work with any `ClientInterface` implementation you provide.

---

## When should I use this?

**Good fit:**
- External APIs are a core part of the application
- You have multiple actions per provider
- You want a clear separation between provider DTOs and domain objects
- You are already following DDD, Hexagonal, Clean Architecture, or similar patterns

**Probably unnecessary:**
- One or two simple HTTP calls
- Small scripts or short-lived prototypes
- Applications where external APIs are not strategically important

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
php bin/console make:integration Github GetUser
```

The command asks:

1. **Base URL** (first run only): `https://api.github.com`
2. **Path**: `/users/{username}`
3. **Method**: `GET`

Generated files:

```
config/packages/integration_engine.yaml
src/Infrastructure/Integrations/Github/
    GithubIntegration.php
    Github.yaml
    GetUser/
        Request/GetUserAction.php
        Response/GetUserMapper.php
        Response/GetUserResponse.php
```

The same command adds new actions to an existing integration — it detects
what already exists and only generates what is missing.

### 2. The correct usage pattern

The integration facade wraps the engine. An application service translates
the integration DTO to a domain object. The controller depends only on the
service:

```php
// 1. Fill in the generated facade
public function getUser(string $username): GetUserResponse
{
    $response = $this->engine->send(
        actionName: GetUserAction::getName(),
        context: DefaultActionContext::create(['username' => $username]),
    );

    \assert($response instanceof GetUserResponse);
    return $response;
}

// 2. Translate to domain in an application service
final class GithubService
{
    public function __construct(
        private readonly GithubIntegration $integration,
    ) {}

    public function getUser(string $username): User
    {
        $dto = $this->integration->getUser($username);

        return new User(
            login:  $dto->login,
            name:   $dto->name,
            email:  $dto->email,
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
      cache_service: ~     # defaults to cache.app — override with a dedicated pool if needed
      client_service: ~    # custom ClientInterface service ID
```

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
Each entry must define the `action` key — the bundle validates this at boot
time and throws a descriptive error if it is missing or not a valid class.

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

## Extensibility

### Custom HTTP adapter

Implement `ClientAdapterInterface` and tag it — the bundle discovers it automatically:

```php
use IntegrationEngine\Core\Contract\ClientAdapterInterface;

final readonly class SoapClientAdapter implements ClientAdapterInterface
{
    public static function getClientType(): string { return 'soap'; }
    public static function requiresPath(): bool    { return false; }
    public static function requiresMethod(): bool  { return false; }

    public function send(AbstractAction $action, ...): array
    {
        // your implementation
    }
}
```

```yaml
# config/packages/integration_engine.yaml
integration_engine:
  integrations:
    my_soap_api:
      client_service: App\Infrastructure\Integrations\SoapClientAdapter
      config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/MySoapApi/MySoapApi.yaml'
```

Project adapters registered after bundle built-ins override them for the
same type — registering a `rest` adapter in your project replaces the
default `SymfonyHttpClientAdapter` for that integration.

### Custom cache pool

```yaml
integration_engine:
  integrations:
    my_api:
      cache_service: cache.my_dedicated_pool
```

Any service implementing `CachePort` (`get` and `set`) is accepted.

---

## Error reference

| Exception | When |
|---|---|
| `ActionNotFoundException` | The action name is not defined in the YAML config |
| `NotMappedActionException` | `hasResponse()` is `true` but `mapper()` returns `null` |
| `MapperActionMismatchException` | The mapper's `getAction()` does not match the action being executed |
| `RequestResponseException` | HTTP 4xx / 5xx or network error |
| `DynamicAuthException` | Token field missing or non-scalar in the auth response |
| `PathResolutionException` | A `{placeholder}` in the path has no matching context key |
| `IntegrationConfigurationException` | Missing or invalid bundle configuration detected at container compile time |

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

## Testing

The bundle ships with a full mutation test suite powered by [Infection](https://infection.github.io/).

```
Mutation Code Coverage: 100%
Covered Code MSI:        94%
```

All production source files are covered. The suite uses inline PHP fakes — no
mocking framework, no coupling to internals. Every test represents a real scenario
the engine can encounter in production.

Run the suite locally:

```bash
XDEBUG_MODE=coverage ./vendor/bin/infection --show-mutations
```

Test suite structure, fakes, and the rationale behind each test:

**[→ TESTING.md](./TESTING.md)**