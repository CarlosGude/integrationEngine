# IntegrationEngine Bundle — Documentation

## 1. Architecture overview

The bundle implements a hexagonal integration engine:

- **Core**: contracts + engine logic
- **Infrastructure**: HTTP, YAML, cache adapters
- **Bundle**: Symfony wiring

## 2. Core execution model

```text
Registry
  -> IntegrationEngine
      -> ConfigPort (YAML / custom source)
      -> Action (immutable)
      -> Context binding (path resolution)
      -> HTTP Client
      -> Mapper
      -> Response DTO
```

## 3. Actions

An Action defines:

- HTTP method
- Path template
- Optional body
- Optional mapper

Actions are immutable and created by the engine.

## 4. Context system

Context is used to resolve dynamic URL segments:

```php
/character/{id}
```

becomes:

```php
/character/1
```

The context is provided at runtime:

```php
->send(
    'GetCharacter',
    context: GetCharacterContext::create(['id' => 1])
)
```

## 5. Body system

Bodies are explicit objects implementing `ActionBodyInterface`:

```php
final class CreateUserBody implements ActionBodyInterface
{
    public static function create(array $data): self {}

    public function toArray(): array {}
}
```

## 6. Engine API

```php
send(
    string $actionName,
    ?ActionBodyInterface $body = null,
    ?ActionContextInterface $context = null
): ResponseInterface
```

### Flow

1. Load action from ConfigPort
2. Apply context (path resolution)
3. Attach body
4. Apply authorization
5. Execute HTTP request
6. Map response
7. Return typed response

## 7. YAML configuration

```yaml
GetUsers:
    action: App\Integration\GetUsersAction
    method: GET
    path: /users

CreateUser:
    action: App\Integration\CreateUserAction
    method: POST
    path: /users
```

No logic lives in YAML.

## 8. Extensibility

You can extend:

- HTTP client
- Cache layer
- Config source

Everything is replaceable via interfaces.

## 9. Design principles

- No magic outside engine
- Actions are immutable
- Context is explicit
- Bodies are typed objects
- Mapping is explicit via mappers
