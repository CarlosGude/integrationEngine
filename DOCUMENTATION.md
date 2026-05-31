# IntegrationEngine Bundle — Documentation

## Table of contents

1. [Architecture overview](#1-architecture-overview)
2. [Core contracts](#2-core-contracts)
3. [The integration YAML](#3-the-integration-yaml)
4. [Authorization](#4-authorization)
5. [The make:integration command](#5-the-makeintegration-command)
6. [Built-in HTTP client](#6-built-in-http-client)
7. [Custom ClientInterface](#7-custom-clientinterface)
8. [Caching](#8-caching)
9. [Multiple integrations](#9-multiple-integrations)
10. [Full worked example](#10-full-worked-example)
11. [Extending the bundle](#11-extending-the-bundle)
12. [Known limitations](#12-known-limitations)

---

## 1. Architecture overview

The bundle follows hexagonal architecture. Dependencies point inward: nothing in Core knows about Symfony, HTTP, or YAML.

```
Bundle (DI wiring)
    └── Infrastructure (adapters: YAML, HTTP, Cache)
            └── Core (contracts, Integration, Registry)
```

**Core** contains only interfaces, abstract classes, and the `Integration` and `IntegrationRegistry` classes. It has zero framework dependencies.

**Infrastructure** provides the concrete adapters: `YamlConfigAdapter`, `SymfonyHttpClientAdapter`, and `InMemoryCacheAdapter`. These implement Core ports and are replaceable.

**Bundle** reads the project's `integration_engine.yaml`, builds service definitions, and registers everything with Symfony's DI container. No business logic lives here.

### Request lifecycle

```
$registry->get(StripeIntegration::NAME)->send('chargeCard', $body)
    │
    ├─ ConfigPort::getAction('chargeCard', $body)
    │       reads stripe.yaml
    │       validates method, path, action class
    │       builds ChargeCardAction with authorization config
    │
    ├─ [if authorization.type = dynamic]
    │       CachePort::has('integration_engine.token.login')?
    │           yes → use cached token, skip login
    │           no  → send login action
    │                 extract token_field from response
    │                 CachePort::set(token, ttl)
    │       rebuild action with StaticAuthorizationConfig(bearer, token)
    │
    ├─ ClientInterface::send(ChargeCardAction)
    │       resolves Authorization header from auth config
    │       sends HTTP request
    │       returns raw decoded array
    │
    └─ AbstractMapper::map(ChargeCardAction, rawArray)
            validates mapper ↔ action pairing
            calls ChargeCardMapper::transform()
            returns typed ChargeCardResponse
```

---

## 2. Core contracts

### `ActionBodyInterface`

Implement on every request DTO. The HTTP client calls `toArray()` to build the JSON body.

```php
final readonly class ChargeCardBody implements ActionBodyInterface
{
    public function __construct(
        public readonly string $token,
        public readonly int    $amount,
        public readonly string $currency = 'usd',
    ) {}

    public function toArray(): array
    {
        return [
            'source'   => $this->token,
            'amount'   => $this->amount,
            'currency' => $this->currency,
        ];
    }
}
```

`toArray()` is only called for `POST`, `PUT`, and `PATCH`. For `GET` and `DELETE` the body is ignored by the built-in client.

---

### `AbstractAction`

Extend this once per action. Declare `getName()` (the string key used in `send()`) and `getMapper()` (the mapper class for this action). The class body is otherwise empty.

```php
final readonly class ChargeCardAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'chargeCard';
    }

    public static function getMapper(): string
    {
        return ChargeCardMapper::class;
    }
}
```

You never instantiate actions directly. `YamlConfigAdapter` builds them from the YAML file and passes them through the engine.

---

### `AbstractMapper`

Extend this to transform the raw API response array into a typed `ResponseInterface`.

```php
final class ChargeCardMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return ChargeCardAction::class;
    }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return new ChargeCardResponse(
            id:       $response['id'],
            status:   $response['status'],
            amount:   $response['amount'],
            currency: $response['currency'],
        );
    }
}
```

`getAction()` declares the action class this mapper belongs to. The engine validates the pairing at runtime and throws `MapperActionMismatchException` if it does not match. `transform()` is `protected` and called only after validation passes. `map()` is `final` — do not override it.

---

### `ResponseInterface`

Implement on every response DTO.

```php
final readonly class ChargeCardResponse implements ResponseInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly int    $amount,
        public readonly string $currency,
    ) {}

    public function toArray(): array
    {
        return [
            'id'       => $this->id,
            'status'   => $this->status,
            'amount'   => $this->amount,
            'currency' => $this->currency,
        ];
    }
}
```

`toArray()` is used by the dynamic auth mechanism to extract the token field. For regular responses, return whatever shape is useful to your application.

---

### `ClientInterface`

```php
interface ClientInterface
{
    public function send(AbstractAction $action): array;
}
```

Receives the fully built action (with method, path, body, and resolved authorization) and must return the decoded response as a plain PHP array. The built-in `SymfonyHttpClientAdapter` covers most cases. Implement your own when you need request signing, retries, multipart uploads, or other non-standard behaviour.

---

### `IntegrationName`

A marker interface that provides the `NAME` constant used as the registry key.

```php
final class StripeIntegration implements IntegrationName
{
    public const string NAME = 'stripe';
}

// Usage:
$registry->get(StripeIntegration::NAME)->send('chargeCard', $body);
```

Using the constant prevents magic strings from scattering across the codebase. The `make:integration` command generates this class automatically.

---

## 3. The integration YAML

Each integration has its own YAML file. Top-level keys are action names that map directly to the first argument of `Integration::send()`.

```yaml
# src/Integration/Stripe/config/stripe.yaml

chargeCard:
    action: App\Integration\Stripe\Action\ChargeCardAction
    method: POST
    path: /v1/charges

getCharge:
    action: App\Integration\Stripe\Action\GetChargeAction
    method: GET
    path: /v1/charges/{id}
```

### Required keys per action

| Key | Description |
|---|---|
| `action` | Fully qualified class name extending `AbstractAction` |
| `method` | HTTP method: `GET`, `POST`, `PUT`, `DELETE` |
| `path` | URL path appended to `base_url` |

### Optional keys

| Key | Description |
|---|---|
| `authorization` | Auth config block — see [Authorization](#4-authorization) |

> The `mapper` key present in version 1 has been removed. The mapper is now declared directly on the action class via `getMapper()`, which makes the YAML shorter and the pairing statically verifiable.

---

## 4. Authorization

### Static authorization

The token is a fixed value (or an environment variable resolved at boot time).

**Bearer token:**
```yaml
chargeCard:
    action: App\Integration\Stripe\Action\ChargeCardAction
    method: POST
    path: /v1/charges
    authorization:
        type: bearer
        token: '%env(STRIPE_API_KEY)%'
```

**API key header:**
```yaml
getWeather:
    action: App\Integration\Weather\Action\GetWeatherAction
    method: GET
    path: /current
    authorization:
        type: api_key
        header: X-RapidAPI-Key
        token: '%env(RAPIDAPI_KEY)%'
```

**HTTP Basic:**
```yaml
getReport:
    action: App\Integration\Reports\Action\GetReportAction
    method: GET
    path: /reports
    authorization:
        type: basic
        username: '%env(API_USER)%'
        password: '%env(API_PASS)%'
```

`SymfonyHttpClientAdapter` handles `bearer`, `api_key`, and `basic` out of the box. Any other type requires a custom `ClientInterface`.

---

### Dynamic authorization

Use this when the API requires a login request to obtain a short-lived token (JWT, session token, etc.).

```yaml
# Auth action — defined like any other action, no authorization block
login:
    action: App\Integration\Acme\Action\LoginAction
    method: POST
    path: /auth/token

# Protected action
getOrders:
    action: App\Integration\Acme\Action\GetOrdersAction
    method: GET
    path: /orders
    authorization:
        type: dynamic
        action: login       # name of the auth action in this same YAML
        token_field: token  # key to extract from the auth response's toArray()
        ttl: 3600           # seconds to cache the token
```

**How it works:**

1. On the first call the engine sends the `login` action and stores the token in the cache.
2. The token is injected as a `bearer` `StaticAuthorizationConfig` into the main action.
3. Subsequent calls within the TTL window skip the login entirely.
4. When the TTL expires the engine re-authenticates transparently on the next call.

The `TokenMapper` must return a `ResponseInterface` whose `toArray()` includes the `token_field` key:

```php
final class TokenMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return LoginAction::class;
    }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return new TokenResponse(token: $response['access_token']);
    }
}

final readonly class TokenResponse implements ResponseInterface
{
    public function __construct(public readonly string $token) {}

    public function toArray(): array
    {
        return ['token' => $this->token]; // key must match token_field in the YAML
    }
}
```

> **Production note:** Dynamic auth requires a persistent cache. `InMemoryCacheAdapter` is process-scoped and will re-authenticate on every request under PHP-FPM. See [Caching](#8-caching).

---

## 5. The make:integration command

Generates the complete file skeleton for a new integration in one command.

```bash
php bin/console make:integration <Name> <FirstAction> [options]
```

### Arguments

| Argument | Description |
|---|---|
| `Name` | Integration name in PascalCase (e.g. `Stripe`, `AcmeApi`) |
| `FirstAction` | First action name in PascalCase (e.g. `ChargeCard`, `GetOrders`) |

### Options

| Option | Default | Description |
|---|---|---|
| `--namespace` | `App\Integration` | Base PHP namespace |
| `--path` | `src/Integration` | Base directory relative to project root |

### Example

```bash
php bin/console make:integration AcmeErp GetOrders \
    --namespace="App\Integration" \
    --path="src/Integration"
```

Generates:

```
src/Integration/AcmeErp/
    AcmeErpIntegration.php          ← IntegrationName constant
    AcmeErpHttpClient.php           ← extend point for custom HTTP behaviour
    Action/GetOrdersAction.php
    Body/GetOrdersBody.php
    Mapper/GetOrdersMapper.php
    Response/GetOrdersResponse.php
    config/acmeerp.yaml
```

The command never overwrites existing files. Running it again with a different action name on an existing integration is safe — only missing files are created.

### After generation

1. Fill in request fields in `Body/GetOrdersBody.php`
2. Fill in response fields in `Response/GetOrdersResponse.php`
3. Implement the mapping in `Mapper/GetOrdersMapper.php`
4. Set `method` and `path` in `config/acmeerp.yaml`
5. Register in `config/packages/integration_engine.yaml` (the command prints the exact snippet)

---

## 6. Built-in HTTP client

`SymfonyHttpClientAdapter` wraps Symfony's `HttpClientInterface` and handles:

- Building `Authorization` headers from `StaticAuthorizationConfig` (`bearer`, `basic`, `api_key`)
- Sending the body as JSON for `POST`, `PUT`, `PATCH`
- Throwing `RequestResponseException` on HTTP 4xx/5xx responses
- Wrapping network-level errors in `RequestResponseException` with status code `0`

Enable it by setting `base_url` in the bundle config:

```yaml
integration_engine:
    integrations:
        stripe:
            config_path: '%kernel.project_dir%/src/Integration/Stripe/config/stripe.yaml'
            base_url: '%env(STRIPE_BASE_URL)%'
```

### Extending the built-in client

If you need to add custom headers, logging, or retry logic without replacing the client entirely, extend `SymfonyHttpClientAdapter`:

```php
final class StripeHttpClient extends SymfonyHttpClientAdapter
{
    public function send(AbstractAction $action): array
    {
        // pre-processing, extra headers, etc.
        return parent::send($action);
    }
}
```

Register it as a service and point `client_service` to it (see [Custom ClientInterface](#7-custom-clientinterface)).

---

## 7. Custom ClientInterface

For complete control, implement `ClientInterface` directly.

```php
final class StripeHttpClient implements ClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {}

    public function send(AbstractAction $action): array
    {
        $auth = $action->getAuthorization();

        $response = $this->httpClient->request(
            $action->getMethod(),
            'https://api.stripe.com' . $action->getPath(),
            [
                'auth_bearer' => $auth instanceof StaticAuthorizationConfig
                    ? $auth->params['token']
                    : throw new \RuntimeException('Missing bearer token'),
                'json' => $action->getBody()?->toArray() ?? [],
            ],
        );

        return $response->toArray();
    }
}
```

Register it as a Symfony service and reference it via `client_service`:

```yaml
integration_engine:
    integrations:
        stripe:
            config_path: '%kernel.project_dir%/src/Integration/Stripe/config/stripe.yaml'
            client_service: App\Integration\Stripe\StripeHttpClient
```

When `client_service` is set, `base_url` is ignored.

---

## 8. Caching

The cache is used exclusively for dynamic authorization tokens.

### Default: InMemoryCacheAdapter

Stores tokens in a PHP array. Suitable for development, CLI commands, and test suites. **Not suitable for production use with dynamic auth under PHP-FPM**, because the array is destroyed at the end of each request.

### Production: persistent cache

Implement `CachePort` with a backend that survives between requests:

```php
// src/Cache/SymfonyCacheAdapter.php
use IntegrationEngine\Core\Port\CachePort;
use Symfony\Contracts\Cache\CacheInterface;

final class SymfonyCacheAdapter implements CachePort
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {}

    public function get(string $key): mixed
    {
        return $this->cache->getItem($key)->get();
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        $item = $this->cache->getItem($key);
        $item->set($value)->expiresAfter($ttl);
        $this->cache->save($item);
    }

    public function has(string $key): bool
    {
        return $this->cache->getItem($key)->isHit();
    }
}
```

Register it and point `cache_service` to it on integrations that use dynamic auth:

```yaml
integration_engine:
    integrations:
        acme_erp:
            config_path: '%kernel.project_dir%/src/Integration/AcmeErp/config/acme_erp.yaml'
            base_url: '%env(ACME_ERP_BASE_URL)%'
            cache_service: App\Cache\SymfonyCacheAdapter
```

Integrations that do not use dynamic auth are unaffected by the cache setting.

---

## 9. Multiple integrations

Each integration is fully independent: its own YAML, HTTP client, and cache.

```yaml
integration_engine:
    integrations:

        stripe:
            config_path: '%kernel.project_dir%/src/Integration/Stripe/config/stripe.yaml'
            base_url: '%env(STRIPE_BASE_URL)%'

        sendgrid:
            config_path: '%kernel.project_dir%/src/Integration/SendGrid/config/sendgrid.yaml'
            base_url: '%env(SENDGRID_BASE_URL)%'

        acme_erp:
            config_path: '%kernel.project_dir%/src/Integration/AcmeErp/config/acme_erp.yaml'
            base_url: '%env(ACME_ERP_BASE_URL)%'
            cache_service: App\Cache\SymfonyCacheAdapter  # needs persistent cache for JWT
```

Access them by name through the registry:

```php
$this->registry->get(StripeIntegration::NAME)->send('chargeCard', $body);
$this->registry->get(SendGridIntegration::NAME)->send('sendEmail', $body);
$this->registry->get(AcmeErpIntegration::NAME)->send('getOrders', $body);
```

---

## 10. Full worked example

**Scenario:** an ERP API that requires a JWT login before every call, with token caching.

### File structure

```
src/Integration/AcmeErp/
    AcmeErpIntegration.php
    AcmeErpHttpClient.php
    Action/
        LoginAction.php
        GetOrdersAction.php
    Body/
        LoginBody.php
        GetOrdersBody.php
    Mapper/
        TokenMapper.php
        OrdersMapper.php
    Response/
        TokenResponse.php
        OrdersResponse.php
    config/
        acme_erp.yaml
```

### AcmeErpIntegration.php

```php
final class AcmeErpIntegration implements IntegrationName
{
    public const string NAME = 'acme_erp';
}
```

### LoginAction.php

```php
final readonly class LoginAction extends AbstractAction
{
    public static function getName(): string  { return 'login'; }
    public static function getMapper(): string { return TokenMapper::class; }
}
```

### LoginBody.php

```php
final readonly class LoginBody implements ActionBodyInterface
{
    public function __construct(
        private readonly string $username,
        private readonly string $password,
    ) {}

    public function toArray(): array
    {
        return ['username' => $this->username, 'password' => $this->password];
    }
}
```

### TokenMapper.php

```php
final class TokenMapper extends AbstractMapper
{
    public static function getAction(): string { return LoginAction::class; }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return new TokenResponse(token: $response['access_token']);
    }
}
```

### TokenResponse.php

```php
final readonly class TokenResponse implements ResponseInterface
{
    public function __construct(public readonly string $token) {}

    public function toArray(): array
    {
        return ['token' => $this->token]; // must match token_field in the YAML
    }
}
```

### GetOrdersAction.php

```php
final readonly class GetOrdersAction extends AbstractAction
{
    public static function getName(): string  { return 'getOrders'; }
    public static function getMapper(): string { return OrdersMapper::class; }
}
```

### GetOrdersBody.php

```php
final readonly class GetOrdersBody implements ActionBodyInterface
{
    public function __construct(
        public readonly string $status = 'pending',
        public readonly int    $page   = 1,
    ) {}

    public function toArray(): array
    {
        return ['status' => $this->status, 'page' => $this->page];
    }
}
```

### OrdersMapper.php

```php
final class OrdersMapper extends AbstractMapper
{
    public static function getAction(): string { return GetOrdersAction::class; }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return new OrdersResponse(
            orders: $response['data'],
            total:  $response['meta']['total'],
        );
    }
}
```

### OrdersResponse.php

```php
final readonly class OrdersResponse implements ResponseInterface
{
    public function __construct(
        public readonly array $orders,
        public readonly int   $total,
    ) {}

    public function toArray(): array
    {
        return ['orders' => $this->orders, 'total' => $this->total];
    }
}
```

### acme_erp.yaml

```yaml
login:
    action: App\Integration\AcmeErp\Action\LoginAction
    method: POST
    path: /auth/token

getOrders:
    action: App\Integration\AcmeErp\Action\GetOrdersAction
    method: GET
    path: /orders
    authorization:
        type: dynamic
        action: login
        token_field: token
        ttl: 3600
```

### config/packages/integration_engine.yaml

```yaml
integration_engine:
    integrations:
        acme_erp:
            config_path: '%kernel.project_dir%/src/Integration/AcmeErp/config/acme_erp.yaml'
            base_url: '%env(ACME_ERP_BASE_URL)%'
            cache_service: App\Cache\SymfonyCacheAdapter
```

### Usage

```php
$orders = $this->registry
    ->get(AcmeErpIntegration::NAME)
    ->send(GetOrdersAction::getName(), new GetOrdersBody(status: 'pending', page: 1));

// $orders is a typed OrdersResponse
foreach ($orders->orders as $order) {
    // ...
}
```

The engine handles login transparently on the first call and caches the JWT for one hour.

---

## 11. Extending the bundle

### Add a custom auth type

Handle it in a custom `ClientInterface` or in a subclass of `SymfonyHttpClientAdapter`:

```php
// In your custom client:
$auth = $action->getAuthorization();

if ($auth instanceof StaticAuthorizationConfig && $auth->type === 'hmac') {
    $payload   = json_encode($action->getBody()?->toArray() ?? []);
    $signature = hash_hmac('sha256', $payload, $auth->params['secret']);
    $headers['X-Signature'] = $signature;
}
```

Declare it in the YAML like any other static auth type:

```yaml
authorization:
    type: hmac
    secret: '%env(API_SECRET)%'
```

### Replace the config source

Implement `ConfigPort` to load actions from a database, remote config store, or any other source:

```php
final class DatabaseConfigAdapter implements ConfigPort
{
    public function getAction(string $actionName, ?ActionBodyInterface $body = null): AbstractAction
    {
        $row = $this->db->findOneBy('integration_actions', ['name' => $actionName])
            ?? throw new \InvalidArgumentException(sprintf('Action "%s" not found.', $actionName));

        return $row['action']::create(
            method: $row['method'],
            path:   $row['path'],
            body:   $body,
        );
    }
}
```

Register it as a service and alias `ConfigPort` to it for the integrations that need it.

---

## 12. Known limitations

### InMemoryCacheAdapter is not suitable for production dynamic auth

Under PHP-FPM each HTTP request runs in a separate process. `InMemoryCacheAdapter` stores tokens in a PHP array that is destroyed at the end of the process. This means the login action is called on every request, defeating the purpose of token caching.

**Fix:** provide a `cache_service` backed by Redis, APCu, or Symfony's cache component for any integration that uses `authorization.type: dynamic`. See [Caching](#8-caching).

### Path parameters are not resolved automatically

If a YAML path contains placeholders (e.g. `/v1/charges/{id}`), the built-in `SymfonyHttpClientAdapter` does not substitute them. You must either build the path in the action or override the client to interpolate parameters from the body.

### Only JSON responses are supported by the built-in client

`SymfonyHttpClientAdapter` calls `$response->toArray()`, which expects a JSON response. For XML, CSV, or binary responses, implement a custom `ClientInterface`.

### PATCH is not listed as a valid method

`AbstractAction::METHODS` includes `GET`, `POST`, `PUT`, and `DELETE`. `PATCH` is handled correctly by the HTTP client but will be rejected by `AbstractAction::create()`. Add `PATCH` to the `METHODS` constant if your integrations require it.
