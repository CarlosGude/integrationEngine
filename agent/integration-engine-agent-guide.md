# Integration Engine — AI Agent Guide

> **Purpose:** Provide an AI agent with all the context needed to understand, navigate, and generate code for the `IntegrationEngine` bundle (Symfony + PHP 8.2+). This document serves as a feed to autonomously create integrations with external APIs.

---

## 1. System overview

The project is an **HTTP integration engine** built on top of Symfony. Its goal is to provide a uniform abstraction layer for consuming external APIs. Each external API is modelled as an **integration**, and each endpoint of that API is modelled as an **action**.

The engine is the `carlosgude/integration-engine` package. The concrete integration code lives in `src/Infrastructure/Integrations/`.

### Key technologies

| Technology | Version | Role |
|---|---|---|
| PHP | ≥ 8.2 | Primary language |
| Symfony | ≥ 7.0 | Framework |
| IntegrationEngine Bundle | latest | Integration engine |
| Symfony HttpClient | bundled | HTTP layer |

---

## 2. Integration architecture


![IntegrationEngine — arquitectura interna](./docs/diagrams/02-internal-architecture.svg)


Every integration follows a strict directory structure under `src/Infrastructure/Integrations/{IntegrationName}/`:

```
src/Infrastructure/Integrations/{Name}/
├── {Name}Integration.php          ← Integration identifier (NAME constant)
├── {Name}.yaml                    ← Action map (path, method, action class)
└── {ActionName}/
    ├── Request/
    │   └── {ActionName}Action.php ← Action definition
    └── Response/
        ├── {ActionName}Response.php ← Integration DTO (ResponseInterface)
        └── {ActionName}Mapper.php   ← array → ResponseInterface transformation
```

Shared DTOs used by multiple actions live in a `Dto/` directory:

```
src/Infrastructure/Integrations/{Name}/
└── Dto/
    └── {EntityName}.php           ← Shared DTO (one per object schema)
```

---

## 3. Engine contracts

All contracts live under the `IntegrationEngine\Core\Contract\` namespace.

### 3.1 `IntegrationName` (interface)

Identifies an integration via a `NAME` constant.

```php
namespace IntegrationEngine\Core\Registry;

interface IntegrationName
{
    public const string NAME = 'snake_case_name'; // must match integration_engine.yaml key
}
```

### 3.2 `AbstractAction`

Defines one endpoint. Actions are **stateless and immutable** — all constructor
properties are `readonly`. The engine instantiates them via `Action::create()`.
The developer never instantiates an Action directly.

```php
abstract class AbstractAction
{
    // Unique name. Must match the key in {Name}.yaml.
    abstract public static function getName(): string;

    // true if the response has content to map.
    abstract public static function hasResponse(): bool;

    // FQCN of the mapper. null only if hasResponse() is false.
    abstract public static function mapper(): ?string;
}
```

**Path parameters** are NOT constructor properties of the Action. They are
resolved at call time via `ActionContextInterface`. The Action itself has no
constructor parameters beyond what the engine injects internally.

```php
// ✔ Correct — stateless action, no constructor
final class GetEntityAction extends AbstractAction
{
    public static function getName(): string   { return 'GetEntity'; }
    public static function hasResponse(): bool { return true; }
    public static function mapper(): ?string   { return GetEntityMapper::class; }
}

// ❌ Wrong — path params do NOT go in the Action constructor
final class GetEntityAction extends AbstractAction
{
    private function __construct(public readonly int $id) {}
    // ...
}
```

### 3.3 `ActionContextInterface`

Carries dynamic path parameters at call time. The engine passes it to
`getPath()` to resolve `{param}` placeholders.

```php
// Use DefaultActionContext for simple key-value params (built-in):
DefaultActionContext::create(['id' => 42])

// Or implement ActionContextInterface for validation and domain logic:
final readonly class GetEntityContext implements ActionContextInterface
{
    private function __construct(private int $id) {}

    public static function create(array $data): self
    {
        return new self(id: (int) $data['id']);
    }

    public function toArray(): array
    {
        return ['id' => $this->id];
    }
}
```

### 3.4 `ActionBodyInterface`

Carries the request payload for `POST`, `PUT`, `PATCH`. Serialised
as JSON automatically by the engine.

```php
final class CreateEntityBody implements ActionBodyInterface
{
    public static function create(array $data): self { ... }
    public function toArray(): array { ... }
}
```

### 3.5 `AbstractMapper`

Transforms the raw API response array into a `ResponseInterface` object.

```php
abstract class AbstractMapper
{
    // FQCN of the Action this mapper handles. Verified by the engine at runtime.
    abstract public static function getAction(): string;

    // Receives the executed action and the decoded JSON array.
    abstract protected static function transform(AbstractAction $action, array $response): ResponseInterface;
}
```

### 3.6 `ResponseInterface`

Every integration DTO must implement this contract. It is an infrastructure
object — it reflects the external API, not domain objects.

```php
interface ResponseInterface
{
    /** @return array<string, mixed> */
    public function toArray(): array;
}
```

`toArray()` is used internally by the engine for dynamic auth token extraction.
It is not the public API of the DTO — expose typed properties on the concrete
class instead.

### 3.7 `ClientAdapterInterface`

Implement this to register a custom HTTP adapter (SOAP, XML-RPC, etc.).
Extends `ClientInterface` so it also requires `send()`.

```php
interface ClientAdapterInterface extends ClientInterface
{
    // Identifier used in integration_engine.yaml client: key
    public static function getClientType(): string;

    // Whether the scaffolding should ask for a path (REST: true, GraphQL/SOAP: false)
    public static function requiresPath(): bool;

    // Whether the scaffolding should ask for an HTTP method (REST: true, GraphQL/SOAP: false)
    public static function requiresMethod(): bool;
}
```

Register in `services.yaml`:

```yaml
App\Infrastructure\Http\SoapClientAdapter:
    tags:
        - { name: integration_engine.client_adapter }
```

Project adapters override bundle built-ins for the same `getClientType()`.

### 3.8 `GraphQLBodyInterface`

Implement for GraphQL queries and mutations. Extends `ActionBodyInterface`.

```php
final class GetUserBody implements GraphQLBodyInterface
{
    public function getQuery(): string
    {
        // Inline or loaded from an external file — both are valid:
        // return 'query { user { id } }';
        return file_get_contents(__DIR__ . '/queries/get_user.graphql');
    }

    public function getVariables(): array { return ['login' => $this->login]; }
    public function toArray(): array { return ['query' => $this->getQuery(), 'variables' => $this->getVariables()]; }
    public static function create(array $data): self { return new self((string) $data['login']); }
}
```

### 3.9 `RequestHeadersInterface`

Optional per-request headers (correlation IDs, tenant identifiers, etc.).

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

---

## 4. Configuration files

### 4.1 `config/packages/integration_engine.yaml`

Registers each integration with the engine. One block per integration.

```yaml
integration_engine:
    integrations:
        {snake_name}:                    # must match {Name}Integration::NAME
            base_url: 'https://api.example.com'
            config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/ExternalApi/ExternalApi.yaml'
            headers:                       # optional — sent with every request
                X-Api-Version: '2'
            cache_service: ~               # optional — defaults to Psr6CacheAdapter wrapping cache.app
            client_service: ~              # optional — custom ClientInterface service ID
```

> **Warning**: The default `Psr6CacheAdapter` wraps `cache.app`. Under PHP-FPM
> this is typically APCu or filesystem — sufficient for most cases. For dynamic
> auth tokens with strict TTL control, configure `cache_service` with a
> dedicated Redis or APCu pool.

### 4.2 `{Name}.yaml` (action map)

Maps each action name to its class, HTTP method, and path.

```yaml
GetEntities:
    action: App\Infrastructure\Integrations\ExternalApi\GetEntities\Request\GetEntitiesAction
    method: GET
    path: /{dtoVar}

GetEntity:
    action: App\Infrastructure\Integrations\ExternalApi\GetEntity\Request\GetEntityAction
    method: GET
    path: /{dtoVar}/{id}    # {id} resolved from ActionContextInterface at call time

CreateEntity:
    action: App\Infrastructure\Integrations\ExternalApi\CreateEntity\Request\CreateEntityAction
    method: POST
    path: /{dtoVar}

FilterEntities:
    action: App\Infrastructure\Integrations\ExternalApi\FilterEntities\Request\FilterEntitiesAction
    method: GET
    path: /{dtoVar}?name={name}&status={status}    # query string placeholders work too
```

No logic lives in YAML. YAML declares intent; Actions and Mappers implement
behaviour.

### Query string placeholders — required vs optional params

The `defaultResolvePath` resolver uses the regex `/\{(\w+)\}/` on the **full path string**,
including the query string portion. This means `{param}` placeholders work identically
whether they appear in the path segment or after `?`.

**Use YAML query string placeholders when all filter params are required** — the engine
throws `PathResolutionException::missingParameter` for any `{placeholder}` not present
in the context:

```yaml
# ✔ All params guaranteed to be present at call time
FilterEntities:
    path: /entities?name={name}&status={status}
```

```php
// ✔ Clean action — no resolvePathCallback needed
final class FilterEntitiesAction extends AbstractAction
{
    public static function getName(): string   { return 'FilterEntities'; }
    public static function hasResponse(): bool { return true; }
    public static function mapper(): ?string   { return FilterEntitiesMapper::class; }
}
```

**Use `resolvePathCallback` when filter params are optional** — build the query string
programmatically, filtering out empty values:

```php
// ✔ Correct — optional params require manual query string building
protected function resolvePathCallback(): ?callable
{
    return static function (string $path, ?ActionContextInterface $context): string {
        $data    = $context?->toArray() ?? [];
        $allowed = ['name', 'status', 'page'];
        $params  = array_filter(
            array_intersect_key($data, array_flip($allowed)),
            static fn(mixed $v): bool => '' !== (string) $v,
        );

        return empty($params) ? '/entities' : '/entities?' . http_build_query($params);
    };
}
```

> **Decision rule:** inspect the API documentation.
> - All filter params marked as **required** → YAML placeholders, no `resolvePathCallback`.
> - Any filter param marked as **optional** → `resolvePathCallback` with `http_build_query`.

---

## 5. Response DTOs

DTOs are `final readonly` classes. They reflect the external API — they are
**not** domain objects.

```php
final readonly class {Dto}
{
    private function __construct(
        public int    $id,
        public string $name,
        public string $status,
        public string $species,
        public array  $origin,   // nested object without its own DTO → array
    ) {}

    /** @param array<string, mixed> $data */
    public static function create(array $data): self
    {
        return new self(
            id:      (int)    ($data['id']      ?? 0),
            name:    (string) ($data['name']    ?? ''),
            status:  (string) ($data['status']  ?? ''),
            species: (string) ($data['species'] ?? ''),
            origin:           ($data['origin']  ?? []),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'      => $this->id,
            'name'    => $this->name,
            'status'  => $this->status,
            'species' => $this->species,
            'origin'  => $this->origin,
        ];
    }
}
```

**OpenAPI type → PHP type mapping:**

| OpenAPI type | PHP type | Cast in `create()` |
|---|---|---|
| `integer` | `int` | `(int) ($data['x'] ?? 0)` |
| `number` | `float` | `(float) ($data['x'] ?? 0.0)` |
| `boolean` | `bool` | `(bool) ($data['x'] ?? false)` |
| `string` | `string` | `(string) ($data['x'] ?? '')` |
| `array` | `array` | `$data['x'] ?? []` |
| nested `object` without own DTO | `array` | `$data['x'] ?? []` |
| nested `object` with own DTO | `DtoClass` | `DtoClass::create($data['x'] ?? [])` |

---

## 6. Common response patterns

### 6.1 Single object response

```php
// Response
final readonly class GetEntityResponse implements ResponseInterface
{
    private function __construct(
        public readonly {Dto} ${dtoVar},
    ) {}

    public static function create({Dto} ${dtoVar}): self
    {
        return new self({dtoVar}: ${dtoVar});
    }

    public function toArray(): array { return $this->{dtoVar}->toArray(); }
}

// Mapper
protected static function transform(AbstractAction $action, array $response): ResponseInterface
{
    return GetEntityResponse::create(
        {dtoVar}: {Dto}::create($response),
    );
}
```

### 6.2 Collection response (wrapper key)

```php
// Response
final readonly class GetEntitiesResponse implements ResponseInterface
{
    /** @param list<{Dto}> ${dtoVar}s */
    private function __construct(
        public readonly array ${dtoVar}s,
    ) {}

    /** @param list<{Dto}> ${dtoVar}s */
    public static function create(array ${dtoVar}s): self
    {
        return new self({dtoVar}s: ${dtoVar}s);
    }

    public function toArray(): array
    {
        return array_map(fn({Dto} $c): array => $c->toArray(), $this->{dtoVar}s);
    }
}

// Mapper — wrapper key ('results', 'products', etc.) comes from the OpenAPI spec
protected static function transform(AbstractAction $action, array $response): ResponseInterface
{
    $items = array_map(
        static fn(array $item): {Dto} => {Dto}::create($item),
        $response['results'] ?? [],
    );

    return GetEntitiesResponse::create({dtoVar}s: $items);
}
```

### 6.3 Empty response

```php
protected static function transform(AbstractAction $action, array $response): ResponseInterface
{
    return new EmptyResponse(); // IntegrationEngine\Core\Response\EmptyResponse
}
```

---

## 7. Authorization

### Static authorization

Declared in the integration YAML under the action entry:

```yaml
GetOrders:
    action: App\Infrastructure\Integrations\MyApi\GetOrders\Request\GetOrdersAction
    method: GET
    path: /orders
    authorization:
        type: bearer
        token: '%env(MY_API_TOKEN)%'
```

Supported types: `bearer`, `basic`, `api_key`.

### Dynamic authorization (OAuth / session tokens)

The auth action is a regular action. The engine executes it, extracts the
token, caches it, and substitutes a static auth transparently.

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
```

The token action requires its own Action + Mapper + Response like any other:

```php
final class FetchTokenAction extends AbstractAction
{
    public static function getName(): string   { return 'FetchToken'; }
    public static function hasResponse(): bool { return true; }
    public static function mapper(): ?string   { return FetchTokenMapper::class; }
}

final readonly class FetchTokenResponse implements ResponseInterface
{
    public function __construct(public readonly string $accessToken) {}
    public function toArray(): array { return ['access_token' => $this->accessToken]; }
}

final class FetchTokenMapper extends AbstractMapper
{
    public static function getAction(): string { return FetchTokenAction::class; }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return new FetchTokenResponse(accessToken: (string) $response['access_token']);
    }
}
```

---

## 8. How to invoke an integration

The entry point is `IntegrationRegistry`, injectable via Symfony DI.
**The recommended pattern is to wrap the registry call in an injectable
service — never call it directly from a controller.**

```php
// Integration facade — lives in Infrastructure
final class ExternalApiIntegration
{
    public const string NAME = '{snake_name}';

    private IntegrationEngine $engine;

    public function __construct(IntegrationRegistry $registry)
    {
        $this->engine = $registry->get(self::NAME);
    }

    // Action without context
    public function get{Dto}s(): GetEntitiesResponse
    {
        $response = $this->engine->send(GetEntitiesAction::getName());
        \assert($response instanceof GetEntitiesResponse);
        return $response;
    }

    // Action with path params via context
    public function get{Dto}(int $id): GetEntityResponse
    {
        $response = $this->engine->send(
            actionName: GetEntityAction::getName(),
            context: DefaultActionContext::create(['id' => $id]),
        );
        \assert($response instanceof GetEntityResponse);
        return $response;
    }

    // Action with body
    public function create{Dto}(CreateEntityBody $body): CreateEntityResponse
    {
        $response = $this->engine->send(
            actionName: CreateEntityAction::getName(),
            body: $body,
        );
        \assert($response instanceof CreateEntityResponse);
        return $response;
    }
}
```

### Anti-Corruption Layer — mandatory pattern

The integration DTO (`GetEntityResponse`) must never reach the domain.
The translation happens in an injectable application service:

```php
// Application service — injectable, decoupled from delivery mechanism
final class {Dto}Service
{
    public function __construct(
        private readonly ExternalApiIntegration $integration,
    ) {}

    public function get{Dto}(int $id): {Dto}  // domain object
    {
        $dto = $this->integration->get{Dto}($id);

        // Service translates. Not the domain, not the controller.
        return new {Dto}(
            id:      $dto->{dtoVar}->id,
            name:    $dto->{dtoVar}->name,
            species: $dto->{dtoVar}->species,
        );
    }
}
```

```php
// ❌ Wrong — domain depends on infrastructure DTO
return {Dto}::fromGetEntityResponse($dto);
```

---

![IntegrationEngine — ciclo de vida de una acción](./docs/diagrams/03-action-lifecycle.svg)

## 9. Full checklist to create an integration

```
[ ] 1.  Obtain the openapi.json (or equivalent) for the target API
[ ] 2.  Run: php bin/console make:integration {Name} {FirstAction}
        → generates Integration, YAML, Action, Mapper, Response skeletons
[ ] 3.  Fill in integration_engine.yaml (base_url, config_path, headers)
[ ] 4.  For each additional operation:
        a. Run: php bin/console make:integration {Name} {ActionName}
           → adds Action, Mapper, Response and appends entry to {Name}.yaml
        b. If the action has query params (filters, search):
           - Inspect the API docs: are the params required or optional?
           - Ask the user to confirm: YAML placeholders (required) vs resolvePathCallback (optional)
           - See section 4.2 "Query string placeholders" for decision rule and examples
        c. Create Dto/{DtoName}.php for shared object schemas
        d. Implement {ActionName}Mapper::transform() with correct wrapper key
        e. Implement {ActionName}Response with typed properties
[ ] 5.  If the API requires auth:
        a. Static: add authorization block to the action entry in {Name}.yaml
        b. Dynamic: create FetchToken action + mapper + response,
           add dynamic authorization block to protected actions
[ ] 6.  Create the integration facade class (wraps registry calls)
[ ] 7.  Create application service(s) that translate DTOs to domain objects
[ ] 8.  Clear cache: php bin/console cache:clear
[ ] 9.  Test via the application service, not directly via the registry
```

---

## 10. Naming conventions

| Concept | Convention | Example |
|---|---|---|
| Integration name | PascalCase | `ExternalApi`, `DummyJson` |
| `Integration::NAME` | snake_case | `{snake_name}` |
| Action name | PascalCase (from `operationId`) | `GetEntities`, `GetEntity` |
| DTO | PascalCase singular | `{Dto}`, `Product`, `Location` |
| Base namespace | `App\Infrastructure\Integrations\{Name}` | |
| YAML key | Same as `getName()` | `GetEntity` |

---

## 11. Complete file reference templates

### `{Name}Integration.php`
```php
<?php declare(strict_types=1);
namespace App\Infrastructure\Integrations\{Name};
use IntegrationEngine\Core\Registry\{IntegrationName, IntegrationRegistry};
use IntegrationEngine\Core\IntegrationEngine;

final class {Name}Integration implements IntegrationName
{
    public const string NAME = '{snake_name}';

    private IntegrationEngine $engine;

    public function __construct(IntegrationRegistry $registry)
    {
        $this->engine = $registry->get(self::NAME);
    }

    // public function {action}(...): {Action}Response { ... }
}
```

### `{Op}Action.php`
```php
<?php declare(strict_types=1);
namespace App\Infrastructure\Integrations\{Name}\{Op}\Request;
use IntegrationEngine\Core\Contract\AbstractAction;
use App\Infrastructure\Integrations\{Name}\{Op}\Response\{Op}Mapper;

final class {Op}Action extends AbstractAction
{
    public static function getName(): string   { return '{Op}'; }
    public static function hasResponse(): bool { return true; }
    public static function mapper(): ?string   { return {Op}Mapper::class; }
}
```

### `Dto/{Dto}.php`
```php
<?php declare(strict_types=1);
namespace App\Infrastructure\Integrations\{Name}\Dto;

final readonly class {Dto}
{
    private function __construct(
        public readonly int    $id,
        public readonly string $name,
    ) {}

    /** @param array<string, mixed> $data */
    public static function create(array $data): self
    {
        return new self(
            id:   (int)    ($data['id']   ?? 0),
            name: (string) ($data['name'] ?? ''),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}
```

### `{Op}Response.php` (single object)
```php
<?php declare(strict_types=1);
namespace App\Infrastructure\Integrations\{Name}\{Op}\Response;
use IntegrationEngine\Core\Contract\ResponseInterface;
use App\Infrastructure\Integrations\{Name}\Dto\{Dto};

final readonly class {Op}Response implements ResponseInterface
{
    private function __construct(
        public readonly {Dto} ${dtoVar},
    ) {}

    public static function create({Dto} ${dtoVar}): self
    {
        return new self({dtoVar}: ${dtoVar});
    }

    public function toArray(): array { return $this->{dtoVar}->toArray(); }
}
```

### `{Op}Response.php` (collection)
```php
<?php declare(strict_types=1);
namespace App\Infrastructure\Integrations\{Name}\{Op}\Response;
use IntegrationEngine\Core\Contract\ResponseInterface;
use App\Infrastructure\Integrations\{Name}\Dto\{Dto};

final readonly class {Op}Response implements ResponseInterface
{
    /** @param list<{Dto}> ${dtoVar}List */
    private function __construct(
        public readonly array ${dtoVar}List,
    ) {}

    /** @param list<{Dto}> ${dtoVar}List */
    public static function create(array ${dtoVar}List): self
    {
        return new self({dtoVar}List: ${dtoVar}List);
    }

    public function toArray(): array
    {
        return array_map(fn({Dto} $item): array => $item->toArray(), $this->{dtoVar}List);
    }
}
```

### `{Op}Mapper.php` (single object)
```php
<?php declare(strict_types=1);
namespace App\Infrastructure\Integrations\{Name}\{Op}\Response;
use IntegrationEngine\Core\Contract\{AbstractAction, AbstractMapper, ResponseInterface};
use App\Infrastructure\Integrations\{Name}\{Op}\Request\{Op}Action;
use App\Infrastructure\Integrations\{Name}\Dto\{Dto};

final class {Op}Mapper extends AbstractMapper
{
    public static function getAction(): string { return {Op}Action::class; }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return {Op}Response::create(
            {dtoVar}: {Dto}::create($response),
        );
    }
}
```

### `{Op}Mapper.php` (collection with wrapper key)
```php
<?php declare(strict_types=1);
namespace App\Infrastructure\Integrations\{Name}\{Op}\Response;
use IntegrationEngine\Core\Contract\{AbstractAction, AbstractMapper, ResponseInterface};
use App\Infrastructure\Integrations\{Name}\{Op}\Request\{Op}Action;
use App\Infrastructure\Integrations\{Name}\Dto\{Dto};

final class {Op}Mapper extends AbstractMapper
{
    public static function getAction(): string { return {Op}Action::class; }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        $items = array_map(
            static fn(array $item): {Dto} => {Dto}::create($item),
            $response['{wrapper_key}'] ?? [],  // e.g. 'results', 'products', 'users'
        );
        return {Op}Response::create({dtoVar}List: $items);
    }
}
```

---

## 12. Agent FAQ

**How do I pass path parameters like `/{dtoVar}/{id}`?**
Via `DefaultActionContext::create(['id' => $value])` passed as the `context`
argument to `engine->send()`. The Action itself has no constructor parameters.
The engine resolves `{id}` from the context at call time.

**The API has filter/search endpoints with query params. Should I use YAML placeholders or `resolvePathCallback`?**
Inspect the API documentation before generating code and **ask the user to confirm** which approach fits:

```
The API declares these filter params for {endpoint}: {param_list}.
→ Are all of them required, or can any be omitted?

  [A] All required  → I will use YAML placeholders: path: /endpoint?name={name}&status={status}
                       No resolvePathCallback needed. Clean action class.
  [B] Some optional → I will use resolvePathCallback with http_build_query to skip empty params.

Which fits this API?
```

Only proceed after the user confirms. Use the following rules to form a recommendation:
- If the API docs mark all params as **required** → recommend A.
- If any param is **optional** or has a default value → recommend B.
- If the documentation is ambiguous → recommend B (safer default: never throws on missing params).

**How do I know which wrapper key to use in the mapper?**
Look at the 200 response schema in the OpenAPI spec: the name of the
`array`-type property is the wrapper key (`results`, `products`, `users`, etc.).

**Can I have multiple actions in one YAML file?**
Yes. Each top-level key in `{Name}.yaml` is a separate action.

**What if the API returns no body (DELETE, 204)?**
`hasResponse()` must return `false` and `mapper()` must return `null`.
No Mapper or Response class is needed.

**When should I use `client:` vs `client_service:`?**
Use `client:` to select a registered protocol adapter (rest, graphql, soap, etc.) — the bundle manages wiring.
Use `client_service:` for a fully custom client (retries, circuit breaking, custom logging) where you want total control.

**Should I call the registry directly from a controller?**
No. Always wrap registry calls in an integration facade, and translate DTOs
to domain objects in an application service. Controllers depend only on
application services.

**When should I create a separate DTO class vs inline array?**
Create a DTO when the object schema appears in multiple responses or has more
than 3-4 fields. For simple nested objects used once, an array is sufficient.

**Where does `services.yaml` fit?**
For standard integrations using `SymfonyHttpClientAdapter`, no `services.yaml`
entry is needed — `base_url` and `headers` are configured directly in
`integration_engine.yaml`. Only add a `services.yaml` entry if you implement
a custom `ClientInterface`.