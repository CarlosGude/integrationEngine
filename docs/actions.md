# Actions

An action declares one API endpoint: its HTTP method, path, and which mapper handles
the response. No logic, no state — purely declarative.

---

## The minimum

Extend `AbstractAction` and implement three static methods:

```php
use IntegrationEngine\Core\Contract\Action\AbstractAction;

final class GetEmployeeAction extends AbstractAction
{
    public static function getName(): string   { return 'GetEmployee'; }
    public static function hasResponse(): bool { return true; }
    public static function mapper(): ?string   { return GetEmployeeMapper::class; }
}
```

Register it in the integration YAML:

```yaml
GetEmployee:
    action: App\Infrastructure\Integrations\MyApi\GetEmployee\Request\GetEmployeeAction
    method: GET
    path:   /employees/{id}
```

That's it. The engine resolves the rest.

---

## The three methods

| Method | Returns | Purpose |
|---|---|---|
| `getName()` | `string` | Key used in YAML and when calling `send()` / building an `EngineRequest` |
| `hasResponse()` | `bool` | `false` for write actions (DELETE, fire-and-forget POST) — engine returns `EmptyResponse` |
| `mapper()` | `?string` | Fully qualified mapper class. `null` only when `hasResponse()` is `false` |

**`hasResponse(): false` still executes the HTTP request** — it just skips the mapping
step and returns `EmptyResponse`. Use it for endpoints that return 204 or an empty body.

---

## YAML options

```yaml
ActionName:
    action:  App\...\ActionClass   # required — fully qualified class name
    method:  GET                   # optional — defaults to POST
    path:    /resource/{id}        # optional — defaults to /
    client:  rest                  # optional — rest (default) or graphql
    authorization:                 # optional — see docs/authorization.md
        type: bearer
        token: '%env(TOKEN)%'
    body:    App\...\BodyClass     # optional — class implementing ActionBodyInterface
```

---

## Stateless by design

Actions are **immutable value objects**. The engine creates them via the internal
`Action::create()` factory — you never instantiate them directly with `new`.

Runtime values — path parameters, request body, correlation IDs, per-request headers —
never belong in the action. They travel through separate channels:

| Runtime value | Channel |
|---|---|
| Path parameters / filters | `ActionContextInterface` |
| Request payload | `ActionBodyInterface` |
| Per-request headers | `RequestHeadersInterface` |

This means the same action class can safely serve concurrent requests without any shared
mutable state. Two calls to `send()` with different contexts produce independent
executions — the action is never mutated between them.

The `getName()` return value must match the key in the YAML exactly — it is the
lookup key used by `ConfigPort::getAction()`.
