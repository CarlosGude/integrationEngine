# Context and Path Resolution

Context carries the runtime values that fill in the path — IDs, filters, pagination.
The engine resolves `{placeholder}` tokens in the YAML path from two sources, in
priority order: the action's **body** first, then **context** for whatever the
body didn't supply. This section covers both, starting with body since it's the
simpler default whenever the value is already part of the payload.

---

## Path segments from the body

If the placeholder's value is already a field you're sending — the common case
for `POST`/`PUT`/`PATCH`, and plenty of `GET` actions too — declare a `body:` on
the action instead of a context. The engine fills the placeholder from the body
and removes that key so it isn't also sent as a payload field:

```yaml
UpdateEmployee:
    path: /employees/{id}
    body: App\Infrastructure\Integrations\MyApi\UpdateEmployee\Request\UpdateEmployeeBody
```

```php
$engine->send(
    actionName: UpdateEmployeeAction::getName(),
    body: UpdateEmployeeBody::create(['id' => 42, 'name' => 'New Name']),
);
// → PUT /employees/42, with { "name": "New Name" } as the JSON body — "id" is
//   consumed by the path, not duplicated in the payload
```

A placeholder the body doesn't have a key for is left untouched and falls
through to context resolution below — nothing to configure, it just works. No
`ConfigPort` or context class needed either way.

---

## The minimum (context)

For path segments that aren't part of the body at all — a `GET` with no body,
or a value you don't otherwise need to send — declare a `{placeholder}` in the
YAML and pass `DefaultActionContext`:

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
| Path segment already sent as a body field | `body:` on the action — no context needed |
| Path segment — always required, not in the body | YAML `{placeholder}` + `DefaultActionContext` |
| Query params — all required | YAML `{placeholder}` in query string |
| Query params — any optional | Custom context with `PathResolvableContextInterface` |
| Validation or domain objects at construction | Custom context with `ActionContextInterface` |
| No dynamic values | No context (or `DefaultActionContext::create([])`) |

A placeholder can be resolved by body and context together across different
calls, but not by design for the *same* key — if both happen to supply the
same placeholder, body wins and context's value for that key is never read.
