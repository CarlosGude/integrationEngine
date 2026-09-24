# Context and Path Resolution

Context supplies all runtime values used to build the URL: IDs, filters and pagination.
The engine resolves `{placeholder}` tokens exclusively from `context`. The `body`
contains payload fields and is preserved even when a field matches a placeholder.

## URL parameters and payload

Pass URL parameters in `context` and payload fields in `body`:

```yaml
UpdateEmployee:
    action: App\Infrastructure\Integrations\MyApi\UpdateEmployee\UpdateEmployeeAction
    method: PUT
    path: /employees/{id}
    body: App\Infrastructure\Integrations\MyApi\UpdateEmployee\Request\UpdateEmployeeBody
```

```php
use IntegrationEngine\Core\Contract\Action\DefaultActionContext;

$engine->send(
    actionName: UpdateEmployeeAction::getName(),
    context: DefaultActionContext::create(['id' => 42]),
    body: UpdateEmployeeBody::create(['name' => 'New Name']),
);
// → PUT /employees/42, with { "name": "New Name" } as the JSON body
```

If the API also requires `id` in the payload, include it explicitly in `body`;
it will be sent unchanged. A body field never supplies a missing context value.
No custom context class or `ConfigPort` implementation is needed.

## Requests without a body

Declare a `{placeholder}` in YAML and pass `DefaultActionContext`:

```yaml
GetEmployee:
    path: /employees/{id}
```

```php
use IntegrationEngine\Core\Contract\Action\DefaultActionContext;

$engine->send(
    actionName: GetEmployeeAction::getName(),
    context: DefaultActionContext::create(['id' => 42]),
);
// → GET /employees/42
```

`DefaultActionContext` is a transparent key-value wrapper. Use it for the vast majority
of cases.

---

## Required query parameters

Placeholders work in the query string too — use them when the parameter is always
present:

```yaml
FilterByStatus:
    path: /employees?status={status}
```

```php
DefaultActionContext::create(['status' => 'active'])
// → GET /employees?status=active
```

Missing a placeholder throws `PathResolutionException` immediately, before the HTTP
request is made.

---

## Optional query parameters

When any parameter is optional, implement `PathResolvableContextInterface`. The context
receives the raw YAML path and returns the final URL — or `null` to fall back to the
default `{placeholder}` resolver:

```php
use IntegrationEngine\Core\Contract\Action\PathResolvableContextInterface;

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
        return empty($params) ? null : $path . '?' . http_build_query($params);
    }
}
```

The action stays declarative — path logic lives in the context, not in the action.
Returning an empty string throws `PathResolutionException`; return `null` to delegate.

---

## Custom context with validation

A custom context also makes sense when you want to enforce invariants at construction
time, or accept domain objects instead of raw arrays:

```php
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;

final readonly class GetEmployeeContext implements ActionContextInterface
{
    private function __construct(private int $id) {}

    public static function create(array $data): self
    {
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            throw new \InvalidArgumentException('Employee id must be a positive integer.');
        }
        return new self($id);
    }

    public function toArray(): array { return ['id' => $this->id]; }
}
```

If you find yourself validating or casting values before calling
`DefaultActionContext::create()`, move that logic into a custom context class instead.

---

## Decision table

| Scenario | Approach |
|---|---|
| Path segment | YAML `{placeholder}` + `DefaultActionContext` |
| Query params — all required | YAML `{placeholder}` in query string |
| Query params — any optional | Custom context with `PathResolvableContextInterface` |
| Validation or domain objects at construction | Custom context with `ActionContextInterface` |
| No dynamic values | No context (or `DefaultActionContext::create([])`) |

When migrating an action that previously supplied URL parameters through `body`,
pass those parameters through `context`. Remove them from `body` only if the API
does not require them in the payload.
