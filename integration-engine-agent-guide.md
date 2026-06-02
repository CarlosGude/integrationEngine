# Integration Engine — AI Agent Guide

> **Purpose:** Provide an AI agent with all the context needed to understand, navigate, and generate code within the `develop-integration-engine` project (Symfony 8.1 + PHP 8.4). This document serves as a feed to autonomously create integrations with external APIs.

---

## 1. System overview

The project is an **HTTP integration engine** built on top of Symfony. Its goal is to provide a uniform abstraction layer for consuming external APIs. Each external API is modelled as an **integration**, and each endpoint of that API is modelled as an **action**.

The engine lives in the `carlosgude/integration-engine` package (loaded from `../integrationEngine` via a `path`-type repository). The concrete integration code lives in `src/Infrastructure/Integrations/`.

### Key technologies

| Technology | Version | Role |
|---|---|---|
| PHP | ≥ 8.4 | Primary language |
| Symfony | 8.1 | Framework |
| IntegrationEngine Bundle | @dev | Integration engine |
| Doctrine ORM | ^3.6 | Persistence (optional per integration) |
| Symfony HttpClient | bundled | HTTP layer |

---

## 2. Integration architecture

Every integration follows a strict directory structure under `src/Infrastructure/Integrations/{IntegrationName}/`:

```
src/Infrastructure/Integrations/{Name}/
├── {Name}Integration.php          ← Integration identifier
├── {Name}HttpClient.php           ← Integration-specific HTTP client
├── {Name}.yaml                    ← Action map (path, method, class)
├── Dto/
│   └── {EntityName}.php           ← Response DTO (one per object schema)
└── {ActionName}/
    ├── Request/
    │   └── {ActionName}Action.php ← Action definition
    └── Response/
        ├── {ActionName}Response.php ← Typed response object
        └── {ActionName}Mapper.php   ← array → Response transformation
```

### Real example: RickAndMorty integration

```
src/Infrastructure/Integrations/RickAndMorty/
├── RickAndMortyIntegration.php
├── RickAndMortyHttpClient.php
├── RickAndMorty.yaml
├── Dto/
│   └── Charter.php
├── Collections/
│   └── CharterCollection.php
└── GetCharters/
    ├── Request/
    │   └── GetChartersAction.php
    └── Response/
        ├── GetChartersResponse.php
        └── GetChartersMapper.php
```

---

## 3. Engine contracts (IntegrationEngine)

All contracts live under the `IntegrationEngine\Core\Contract\` namespace.

### 3.1 `IntegrationName` (interface)

Identifies an integration via a `NAME` constant.

```php
namespace IntegrationEngine\Core\Registry;

interface IntegrationName
{
    // Implementing class must declare:
    public const string NAME = 'snake_case_name';
}
```

**Convention:** the `NAME` value (snake_case) must match the key in `config/packages/integration_engine.yaml`.

### 3.2 `AbstractAction`

Defines an action (one endpoint). The engine uses it to build the HTTP request.

```php
namespace IntegrationEngine\Core\Contract;

abstract readonly class AbstractAction
{
    // Unique action name. Must match the key in the YAML map.
    public static function getName(): string;

    // true if the action sends a JSON body in the request.
    public static function hasBody(): bool;

    // true if the response has content to map.
    public static function hasResponse(): bool;

    // FQCN of the mapper. null if hasResponse() is false.
    public static function mapper(): ?string;
}
```

**Actions with parameters** (path/query) declare them in a private constructor and expose a `static ::create()` factory:

```php
final readonly class GetCharacterAction extends AbstractAction
{
    private function __construct(
        public readonly int $id,  // path parameter — name must match {id} in the YAML path
    ) {}

    public static function create(int $id): self
    {
        return new self(id: $id);
    }

    public static function getName(): string      { return 'GetCharacter'; }
    public static function hasBody(): bool        { return false; }
    public static function hasResponse(): bool    { return true; }
    public static function mapper(): ?string      { return GetCharacterMapper::class; }
}
```

### 3.3 `AbstractMapper`

Transforms the raw API response array into a `ResponseInterface` object.

```php
namespace IntegrationEngine\Core\Contract;

abstract class AbstractMapper
{
    // FQCN of the Action this mapper handles.
    public static function getAction(): string;

    // Receives the executed action and the decoded JSON response array.
    protected static function transform(AbstractAction $action, array $response): ResponseInterface;
}
```

### 3.4 `ResponseInterface`

Every response object must implement this contract.

```php
namespace IntegrationEngine\Core\Contract;

interface ResponseInterface
{
    /** @return array<string, mixed>|list<array<string, mixed>> */
    public function toArray(): array;
}
```

### 3.5 `SymfonyHttpClientAdapter`

Base for each integration's HTTP client. Extended without adding any code:

```php
final readonly class {Name}HttpClient extends SymfonyHttpClientAdapter {}
```

---

## 4. Configuration files

### 4.1 `config/packages/integration_engine.yaml`

Registers each integration with the engine. **One block per integration is required.**

```yaml
integration_engine:
    integrations:
        rick_and_morty:                    # Value of {Name}Integration::NAME
            config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/RickAndMorty/RickAndMorty.yaml'
            base_url: 'https://rickandmortyapi.com/api'
```

### 4.2 `{Name}.yaml` (action map)

Maps each action name to its class, HTTP method, and path.

```yaml
GetCharters:                          # Value returned by getName()
    action: App\Infrastructure\Integrations\RickAndMorty\GetCharters\Request\GetChartersAction
    method: GET
    path: /character

GetCharacter:
    action: App\Infrastructure\Integrations\RickAndMorty\GetCharacter\Request\GetCharacterAction
    method: GET
    path: /character/{id}             # {id} is resolved from the Action constructor
```

### 4.3 `config/services.yaml`

Configures the base HTTP client. Every integration using `SymfonyHttpClientAdapter` needs an entry specifying its `base_url`:

```yaml
services:
    App\Infrastructure\Integrations\RickAndMorty\RickAndMortyHttpClient:
        arguments:
            $httpClient: '@http_client'
            $baseUrl: 'https://rickandmortyapi.com/api'
```

---

## 5. Response DTOs

DTOs are `final readonly` classes in `Dto/`. They follow a fixed pattern with a **private constructor** and a `static ::create()` factory:

```php
final readonly class Character
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

The API returns `{ "id": 1, "name": "..." }`.

```php
// Response
final readonly class GetCharacterResponse implements ResponseInterface
{
    private function __construct(
        public readonly Character $character,
    ) {}

    public static function create(Character $character): self
    {
        return new self(character: $character);
    }

    public function toArray(): array { return $this->character->toArray(); }
}

// Mapper
protected static function transform(AbstractAction $action, array $response): ResponseInterface
{
    return GetCharacterResponse::create(
        character: Character::create($response),
    );
}
```

### 6.2 Collection response (wrapper key)

The API returns `{ "results": [ {...}, {...} ] }` or `{ "products": [...] }`.

```php
// Response
final readonly class ListCharactersResponse implements ResponseInterface
{
    /** @param list<Character> $characterList */
    private function __construct(
        public readonly array $characterList,
    ) {}

    /** @param list<Character> $characterList */
    public static function create(array $characterList): self
    {
        return new self(characterList: $characterList);
    }

    public function toArray(): array
    {
        return array_map(fn(Character $c): array => $c->toArray(), $this->characterList);
    }
}

// Mapper — the array key ("results", "products", etc.) comes from the OpenAPI spec
protected static function transform(AbstractAction $action, array $response): ResponseInterface
{
    $items = array_map(
        static fn(array $item): Character => Character::create($item),
        $response['results'] ?? [],
    );

    return ListCharactersResponse::create(characterList: $items);
}
```

### 6.3 Empty response

```php
protected static function transform(AbstractAction $action, array $response): ResponseInterface
{
    return new EmptyResponse();  // IntegrationEngine\Core\Response\EmptyResponse
}
```

---

## 7. How to invoke an integration at runtime

The entry point is `IntegrationRegistry`, injectable via Symfony DI:

```php
use IntegrationEngine\Core\Registry\IntegrationRegistry;

// Inside a Controller or Service:

// Action without parameters:
$response = $this->registry
    ->get(RickAndMortyIntegration::NAME)
    ->send(GetChartersAction::getName())
    ->toArray();

// Action with parameters (Action is built via ::create() and passed as an object):
$response = $this->registry
    ->get(RickAndMortyIntegration::NAME)
    ->send(GetCharacterAction::getName(), GetCharacterAction::create(id: 1))
    ->toArray();
```

---

## 8. Available commands

### `make:integration`

Generates an **empty skeleton** for a new integration (no real DTOs).

```bash
php bin/console make:integration
# Interactive: asks for the integration name and the first action name.
```

---

## 9. Full checklist to create an integration

The agent must follow these steps in order:

```
[ ] 1. Obtain the openapi.json file for the target API
[ ] 2. Create the directory: src/Infrastructure/Integrations/{Name}/
[ ] 3. Create {Name}Integration.php  (implements IntegrationName, declares NAME)
[ ] 4. Create {Name}HttpClient.php   (extends SymfonyHttpClientAdapter)
[ ] 5. For each operation in the spec:
        a. Create {Op}/Request/{Op}Action.php
           - Private constructor with path/query params (if any)
           - Static ::create() factory
        b. Create Dto/{DtoName}.php for every object schema in the response
           - Private constructor
           - Static ::create(array $data): self factory
        c. Create {Op}/Response/{Op}Response.php
           - Private constructor
           - Static ::create() factory
        d. Create {Op}/Response/{Op}Mapper.php
[ ] 6. Create {Name}.yaml with all action entries
[ ] 7. Add block to config/packages/integration_engine.yaml
[ ] 8. Add HttpClient entry to config/services.yaml with $baseUrl
[ ] 9. Clear cache: php bin/console cache:clear
[ ] 10. Test from a Controller using IntegrationRegistry::get()->send()
```

---

## 10. Naming conventions

| Concept | Convention | Example |
|---|---|---|
| Integration name | PascalCase | `RickAndMorty`, `DummyJson` |
| `Integration::NAME` | snake_case | `rick_and_morty` |
| Action name | PascalCase (from `operationId`) | `ListCharacters`, `GetCharacter` |
| DTO | PascalCase singular | `Character`, `Product`, `Location` |
| Base namespace | `App\Infrastructure\Integrations\{Name}` | |
| YAML key | Same as `getName()` | `ListCharacters` |

---

## 11. Complete file reference templates

### `{Name}Integration.php`
```php
<?php declare(strict_types=1);
namespace App\Infrastructure\Integrations\{Name};
use IntegrationEngine\Core\Registry\IntegrationName;

final class {Name}Integration implements IntegrationName
{
    public const string NAME = '{snake_name}';
}
```

### `{Name}HttpClient.php`
```php
<?php declare(strict_types=1);
namespace App\Infrastructure\Integrations\{Name};
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;

final readonly class {Name}HttpClient extends SymfonyHttpClientAdapter {}
```

### `{Op}Action.php` (no parameters)
```php
<?php declare(strict_types=1);
namespace App\Infrastructure\Integrations\{Name}\{Op}\Request;
use IntegrationEngine\Core\Contract\AbstractAction;
use App\Infrastructure\Integrations\{Name}\{Op}\Response\{Op}Mapper;

final readonly class {Op}Action extends AbstractAction
{
    public static function getName(): string    { return '{Op}'; }
    public static function hasBody(): bool      { return false; }
    public static function hasResponse(): bool  { return true; }
    public static function mapper(): ?string    { return {Op}Mapper::class; }
}
```

### `{Op}Action.php` (with path parameters)
```php
<?php declare(strict_types=1);
namespace App\Infrastructure\Integrations\{Name}\{Op}\Request;
use IntegrationEngine\Core\Contract\AbstractAction;
use App\Infrastructure\Integrations\{Name}\{Op}\Response\{Op}Mapper;

final readonly class {Op}Action extends AbstractAction
{
    private function __construct(
        public readonly int $id,  // name must match {id} in the YAML path
    ) {}

    public static function create(int $id): self
    {
        return new self(id: $id);
    }

    public static function getName(): string    { return '{Op}'; }
    public static function hasBody(): bool      { return false; }
    public static function hasResponse(): bool  { return true; }
    public static function mapper(): ?string    { return {Op}Mapper::class; }
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
        // ... other properties
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
        return [
            'id'   => $this->id,
            'name' => $this->name,
        ];
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
            $response['{wrapper_key}'] ?? [],   // e.g. 'results', 'products', 'users'
        );
        return {Op}Response::create({dtoVar}List: $items);
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

---

## 12. Agent FAQ

**How do I know which key to use in the mapper for the items array?**
Look at the 200 response schema in the OpenAPI spec: the name of the `array`-type property is the wrapper key (e.g. `results`, `products`, `users`, `carts`).

**Must Action parameters have the same name as path placeholders in the YAML?**
Yes. If the path is `/character/{id}`, the constructor must have `public readonly int $id`. The engine resolves the substitution via reflection.

**Can I have multiple actions in one YAML file?**
Yes. Each top-level key in `{Name}.yaml` is a separate action.

**When should I create a separate Collection class (like `CharterCollection`)?**
It is optional and only needed when business logic requires typed operations on the collection. Using `list<Dto>` inside the Response is sufficient for most cases.

**What if the API returns no body (DELETE, 204)?**
`hasResponse()` must return `false` and `mapper()` must return `null`. No mapper class is needed.

**Can I add authentication?**
Yes, via `AuthorizationConfig` / `StaticAuthorizationConfig` / `DynamicAuthorizationConfig` from the engine. Concrete configuration details depend on the installed version of `carlosgude/integration-engine`.
