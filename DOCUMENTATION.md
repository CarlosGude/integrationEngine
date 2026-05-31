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

### Generated file structure

The `make:integration` command places each action in its own subdirectory, with a clear Request/Response split:

```
src/Infrastructure/Integrations/Stripe/
    StripeIntegration.php           ← IntegrationName constant
    StripeHttpClient.php            ← extend point for custom HTTP behaviour
    Stripe.yaml                     ← action registry for this integration
    GetCharge/
        Request/
            GetChargeAction.php
            GetChargeBody.php       ← only for POST/PUT
        Response/
            GetChargeMapper.php
            GetChargeResponse.php
    DeleteCharge/
        Request/
            DeleteChargeAction.php  ← DELETE actions have no Response layer
```

### Request lifecycle

```
$registry->get(StripeIntegration::NAME)->send('GetCharge', $body)
    │
    ├─ ConfigPort::getAction('GetCharge', $body)
    │       reads Stripe.yaml
    │       validates action class, method, path
    │       builds GetChargeAction with authorization config
    │
    ├─ [if authorization.type = dynamic]
    │       CachePort::has('integration_engine.token.<auth_action>')?
    │           yes → use cached token, skip login
    │           no  → send auth action
    │                 extract token_field from response via toArray()
    │                 CachePort::set(token, ttl)
    │       rebuild action with StaticAuthorizationConfig(bearer, token)
    │
    ├─ ClientInterface::send(GetChargeAction)
    │       resolves Authorization header from auth config
    │       sends HTTP request
    │       returns raw decoded array
    │
    ├─ [if action::hasResponse() === false]
    │       return EmptyResponse (no mapper invoked)
    │
    └─ AbstractMapper::map(GetChargeAction, rawArray)
            validates mapper ↔ action pairing
            calls GetChargeMapper::transform()
            returns typed GetChargeResponse
```

---

## 2. Core contracts

### `ActionBodyInterface`

Implement on every request DTO. The HTTP client calls `toArray()` to build the JSON body.

```php
final readonly class PostChargeBody implements ActionBodyInterface
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

Extend this once per action. Declare four static methods:

```php
final readonly class GetChargeAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'GetCharge';
    }

    public static function hasBody(): bool
    {
        return false; // true for POST/PUT
    }

    public static function hasResponse(): bool
    {
        return true; // false for DELETE
    }

    public static function mapper(): ?string
    {
        return GetChargeMapper::class; // null when hasResponse() is false
    }
}
```

`getName()` is the string key used in `send()`. `hasBody()` and `hasResponse()` control which layers the engine activates. `mapper()` returns the mapper class string, or `null` for actions without a response (e.g. DELETE).

You never instantiate actions directly. `YamlConfigAdapter` builds them from the YAML file and passes them through the engine.

---

### `AbstractMapper`

Extend this to transform the raw API response array into a typed `ResponseInterface`.

```php
final class GetChargeMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return GetChargeAction::class;
    }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return new GetChargeResponse(
            id:       $response['id'],
            status:   $response['status'],
            amount:   $response['amount'],
            currency: $response['currency'],
        );
    }
}
```

`getAction()` declares the action class this mapper belongs to. The engine validates the pairing at runtime and throws `MapperActionMismatchException` if it does not match. `transform()` is `protected` and called only after validation passes. `map()` is `final` — do not override it.

The mapper is only invoked when `action::hasResponse()` returns `true`. For DELETE actions, `mapper()` returns `null` and no mapper is needed.

---

### `ResponseInterface`

Implement on every response DTO.

```php
final readonly class GetChargeResponse implements ResponseInterface
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
$registry->get(StripeIntegration::NAME)->send('GetCharge', $body);
```

Using the constant prevents magic strings from scattering across the codebase. The `make:integration` command generates this class automatically with a snake_case `NAME` derived from the PascalCase integration name.

---

## 3. The integration YAML

Each integration has a single YAML file (named after the integration). Top-level keys are action names that map directly to the first argument of `Integration::send()`.

```yaml
# src/Infrastructure/Integrations/Stripe/Stripe.yaml

GetCharge:
    action: App\Infrastructure\Integrations\Stripe\GetCharge\Request\GetChargeAction
    method: GET
    path: /v1/charges/{id}

PostCharge:
    action: App\Infrastructure\Integrations\Stripe\PostCharge\Request\PostChargeAction
    method: POST
    path: /v1/charges

DeleteCharge:
    action: App\Infrastructure\Integrations\Stripe\DeleteCharge\Request\DeleteChargeAction
    method: DELETE
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

The mapper is **not** declared in the YAML. It is declared directly on the action class via `mapper()`, which makes the YAML shorter and the pairing statically verifiable.

---

## 4. Authorization

### Static authorization

The token is a fixed value (or an environment variable resolved at boot time).

**Bearer token:**
```yaml
PostCharge:
    action: App\Infrastructure\Integrations\Stripe\PostCharge\Request\PostChargeAction
    method: POST
    path: /v1/charges
    authorization:
        type: bearer
        token: '%env(STRIPE_API_KEY)%'
```

**API key header:**
```yaml
GetWeather:
    action: App\Infrastructure\Integrations\Weather\GetWeather\Request\GetWeatherAction
    method: GET
    path: /current
    authorization:
        type: api_key
        header: X-RapidAPI-Key
        token: '%env(RAPIDAPI_KEY)%'
```

**HTTP Basic:**
```yaml
GetReport:
    action: App\Infrastructure\Integrations\Reports\GetReport\Request\GetReportAction
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
# src/Infrastructure/Integrations/Acme/Acme.yaml

PostLogin:
    action: App\Infrastructure\Integrations\Acme\PostLogin\Request\PostLoginAction
    method: POST
    path: /auth/token

GetOrders:
    action: App\Infrastructure\Integrations\Acme\GetOrders\Request\GetOrdersAction
    method: GET
    path: /orders
    authorization:
        type: dynamic
        action: PostLogin       # name of the auth action in this same YAML
        token_field: token      # key to extract from the auth response's toArray()
        ttl: 3600               # seconds to cache the token
```

**How it works:**

1. On the first call the engine sends the `PostLogin` action and stores the token in the cache under the key `integration_engine.token.PostLogin`.
2. The token is injected as a `bearer` `StaticAuthorizationConfig` into the main action.
3. Subsequent calls within the TTL window skip the login entirely.
4. When the TTL expires the engine re-authenticates transparently on the next call.

The login mapper must return a `ResponseInterface` whose `toArray()` includes the `token_field` key:

```php
final class PostLoginMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return PostLoginAction::class;
    }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return new PostLoginResponse(token: $response['access_token']);
    }
}

final readonly class PostLoginResponse implements ResponseInterface
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

Generates the complete file skeleton for a new integration or appends a new action to an existing one, all in a single interactive command.

```bash
php bin/console make:integration <Name> <Resource> [options]
```

### Arguments

| Argument | Description |
|---|---|
| `Name` | Integration name in PascalCase (e.g. `Stripe`, `AcmeErp`) |
| `Resource` | Resource name in PascalCase (e.g. `Charge`, `Orders`). The HTTP verb is prepended automatically. |

### Options

| Option | Default | Description |
|---|---|---|
| `--namespace` | `App\Infrastructure\Integrations` | Base PHP namespace |
| `--path` | `src/Infrastructure/Integrations` | Base directory relative to project root |

### Interactive prompts

After the arguments, the command asks interactively:

1. **HTTP method** — choice between `GET`, `POST`, `PUT`, `DELETE` (default: `GET`)
2. **Action path** — the URL path, e.g. `/orders/{id}` (required, re-prompts if empty)

The final action name is built by prepending the verb: `Get` + `Charge` = `GetCharge`, `Post` + `Orders` = `PostOrders`.

### Example

```bash
php bin/console make:integration AcmeErp Orders
# → chooses GET
# → enters /orders
# → action name becomes: GetOrders
```

Generates (new integration):

```
src/Infrastructure/Integrations/AcmeErp/
    AcmeErpIntegration.php
    AcmeErpHttpClient.php
    AcmeErp.yaml
    GetOrders/
        Request/
            GetOrdersAction.php
        Response/
            GetOrdersMapper.php
            GetOrdersResponse.php
```

For a **POST** action:

```
src/Infrastructure/Integrations/AcmeErp/
    PostOrders/
        Request/
            PostOrdersAction.php
            PostOrdersBody.php      ← only generated for POST/PUT
        Response/
            PostOrdersMapper.php
            PostOrdersResponse.php
```

For a **DELETE** action:

```
src/Infrastructure/Integrations/AcmeErp/
    DeleteOrders/
        Request/
            DeleteOrdersAction.php  ← no Response layer for DELETE
```

The command never overwrites existing files. Running it again on an existing integration is safe — only missing files are created, and the new action entry is appended to the existing YAML.

### After generation

1. Fill in request fields in `Request/{Action}Body.php` (POST/PUT only)
2. Fill in response fields in `Response/{Action}Response.php`
3. Implement the mapping in `Response/{Action}Mapper.php`
4. Register in `config/packages/integration_engine.yaml` (the command prints the exact snippet)

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
            config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/Stripe/Stripe.yaml'
            base_url: '%env(STRIPE_BASE_URL)%'
```

### Extending the built-in client

`SymfonyHttpClientAdapter` is declared `readonly`. Subclasses must also be `readonly`. The `make:integration` command generates a ready-to-use `{Name}HttpClient.php` stub for each integration:

```php
final readonly class StripeHttpClient extends SymfonyHttpClientAdapter
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
            config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/Stripe/Stripe.yaml'
            client_service: App\Infrastructure\Integrations\Stripe\StripeHttpClient
```

When `client_service` is set, `base_url` is ignored.

---

## 8. Caching

The cache is used exclusively for dynamic authorization tokens.

### Default: InMemoryCacheAdapter

Stores tokens in a PHP array along with their expiry timestamps. Suitable for development, CLI commands, and test suites. **Not suitable for production use with dynamic auth under PHP-FPM**, because the array is destroyed at the end of each request.

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
            config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/AcmeErp/AcmeErp.yaml'
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
            config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/Stripe/Stripe.yaml'
            base_url: '%env(STRIPE_BASE_URL)%'

        sendgrid:
            config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/SendGrid/SendGrid.yaml'
            base_url: '%env(SENDGRID_BASE_URL)%'

        acme_erp:
            config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/AcmeErp/AcmeErp.yaml'
            base_url: '%env(ACME_ERP_BASE_URL)%'
            cache_service: App\Cache\SymfonyCacheAdapter  # needs persistent cache for JWT
```

Access them by name through the registry:

```php
$this->registry->get(StripeIntegration::NAME)->send('GetCharge', $body);
$this->registry->get(SendGridIntegration::NAME)->send('PostEmail', $body);
$this->registry->get(AcmeErpIntegration::NAME)->send('GetOrders', $body);
```

---

## 10. Full worked example

**Scenario:** an ERP API that requires a JWT login before every call, with token caching.

### File structure

```
src/Infrastructure/Integrations/AcmeErp/
    AcmeErpIntegration.php
    AcmeErpHttpClient.php
    AcmeErp.yaml
    PostLogin/
        Request/
            PostLoginAction.php
            PostLoginBody.php
        Response/
            PostLoginMapper.php
            PostLoginResponse.php
    GetOrders/
        Request/
            GetOrdersAction.php
        Response/
            GetOrdersMapper.php
            GetOrdersResponse.php
```

### AcmeErpIntegration.php

```php
final class AcmeErpIntegration implements IntegrationName
{
    public const string NAME = 'acme_erp';
}
```

### PostLoginAction.php

```php
final readonly class PostLoginAction extends AbstractAction
{
    public static function getName(): string    { return 'PostLogin'; }
    public static function hasBody(): bool      { return true; }
    public static function hasResponse(): bool  { return true; }
    public static function mapper(): ?string    { return PostLoginMapper::class; }
}
```

### PostLoginBody.php

```php
final readonly class PostLoginBody implements ActionBodyInterface
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

### PostLoginMapper.php

```php
final class PostLoginMapper extends AbstractMapper
{
    public static function getAction(): string { return PostLoginAction::class; }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return new PostLoginResponse(token: $response['access_token']);
    }
}
```

### PostLoginResponse.php

```php
final readonly class PostLoginResponse implements ResponseInterface
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
    public static function getName(): string    { return 'GetOrders'; }
    public static function hasBody(): bool      { return false; }
    public static function hasResponse(): bool  { return true; }
    public static function mapper(): ?string    { return GetOrdersMapper::class; }
}
```

### GetOrdersMapper.php

```php
final class GetOrdersMapper extends AbstractMapper
{
    public static function getAction(): string { return GetOrdersAction::class; }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return new GetOrdersResponse(
            orders: $response['data'],
            total:  $response['meta']['total'],
        );
    }
}
```

### GetOrdersResponse.php

```php
final readonly class GetOrdersResponse implements ResponseInterface
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

### AcmeErp.yaml

```yaml
PostLogin:
    action: App\Infrastructure\Integrations\AcmeErp\PostLogin\Request\PostLoginAction
    method: POST
    path: /auth/token

GetOrders:
    action: App\Infrastructure\Integrations\AcmeErp\GetOrders\Request\GetOrdersAction
    method: GET
    path: /orders
    authorization:
        type: dynamic
        action: PostLogin
        token_field: token
        ttl: 3600
```

### config/packages/integration_engine.yaml

```yaml
integration_engine:
    integrations:
        acme_erp:
            config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/AcmeErp/AcmeErp.yaml'
            base_url: '%env(ACME_ERP_BASE_URL)%'
            cache_service: App\Cache\SymfonyCacheAdapter
```

### Usage

```php
$orders = $this->registry
    ->get(AcmeErpIntegration::NAME)
    ->send('GetOrders', null);

// $orders is a typed GetOrdersResponse
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
            authorization: null,
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

If a YAML path contains placeholders (e.g. `/v1/charges/{id}`), the built-in `SymfonyHttpClientAdapter` does not substitute them. You must either build the final path in a custom client or pre-resolve the path before passing it to `send()`.

### Only JSON responses are supported by the built-in client

`SymfonyHttpClientAdapter` calls `$response->toArray()`, which expects a JSON response. For XML, CSV, or binary responses, implement a custom `ClientInterface`.

### PATCH is not listed as a valid method in the command

The `make:integration` command only offers `GET`, `POST`, `PUT`, and `DELETE`. The built-in HTTP client does handle `PATCH` correctly (it sends the body as JSON), but you must add the action manually if you need it — the generator will not create the `PATCH` skeleton.