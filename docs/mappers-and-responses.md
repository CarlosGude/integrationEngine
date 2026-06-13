# Mappers and Responses

A mapper transforms the raw HTTP response array into a typed DTO. It is the only place
where external API field names appear in your codebase.

---

## The minimum

Extend `AbstractMapper`, declare which action it belongs to, and implement `transform()`:

```php
use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\AbstractMapper;
use IntegrationEngine\Core\Contract\ResponseInterface;

final class GetEmployeeMapper extends AbstractMapper
{
    public static function getAction(): string { return GetEmployeeAction::class; }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return GetEmployeeResponse::create($response);
    }
}
```

The response DTO must implement `ResponseInterface`:

```php
use IntegrationEngine\Core\Contract\ResponseInterface;

final readonly class GetEmployeeResponse implements ResponseInterface
{
    private function __construct(
        public int    $id,
        public string $name,
    ) {}

    public static function create(array $data): self
    {
        return new self(
            id:   (int)    ($data['id']   ?? 0),
            name: (string) ($data['name'] ?? ''),
        );
    }

    public function toArray(): array { return ['id' => $this->id, 'name' => $this->name]; }
}
```

---

## Type mapping

Match PHP types to what the API returns:

| API type | PHP type | Cast in `create()` |
|---|---|---|
| integer | `int` | `(int) ($data['x'] ?? 0)` |
| number | `float` | `(float) ($data['x'] ?? 0.0)` |
| boolean | `bool` | `(bool) ($data['x'] ?? false)` |
| string | `string` | `(string) ($data['x'] ?? '')` |
| array | `array` | `$data['x'] ?? []` |
| nullable string | `?string` | `is_string($data['x'] ?? null) ? $data['x'] : null` |
| nested object (simple) | `array` | `$data['x'] ?? []` |
| nested object (reused) | `NestedDto` | `NestedDto::create($data['x'] ?? [])` |

Use a dedicated DTO class for nested objects that appear in more than one response, or
that have more than three or four fields. Nested DTOs do **not** implement
`ResponseInterface` — only the top-level class returned from `transform()` does.

---

## One mapper per action

The engine enforces `$mapper::getAction() === $action::class` before calling
`transform()`. Two action classes cannot share the same mapper — this throws
`MapperActionMismatchException`.

When two actions return the same response shape, extract the transformation logic into a
dedicated class and delegate from each mapper:

```php
final class EmployeeCollectionTransformer
{
    public static function transform(array $response): EmployeeListResponse { ... }
}

final class GetEmployeesMapper extends AbstractMapper
{
    public static function getAction(): string { return GetEmployeesAction::class; }
    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return EmployeeCollectionTransformer::transform($response);
    }
}

final class FilterEmployeesMapper extends AbstractMapper
{
    public static function getAction(): string { return FilterEmployeesAction::class; }
    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return EmployeeCollectionTransformer::transform($response);
    }
}
```

---

## `toArray()` — engine contract, not your API

`ResponseInterface` requires `toArray()`. The engine uses it internally to extract
dynamic auth tokens from token responses. It is not your public API.

Consumers of your facade should access typed properties directly:

```php
// ✅ correct — typed access
$name = $response->name;

// ❌ wrong — leaks the engine contract
$name = $response->toArray()['name'];
```

The only time `toArray()` is meaningful to you is when writing a token response for
dynamic auth — the field named in `token_field` must appear in `toArray()`.
See [Authorization](authorization.md) for details.
