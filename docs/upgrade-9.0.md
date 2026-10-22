# Upgrading from v8.x to v9.0

One breaking change: **URL placeholders resolve exclusively from the action context.** A body field never supplies a `{placeholder}` value any more, and it is never removed from the payload because its name matches one.

If none of your actions combine a `{placeholder}` path with a `body:` whose fields carried the URL value, there is nothing to do.

## What changed

| | v8.x | v9.0 |
|---|---|---|
| Source of `{placeholder}` values | the body first, then the context | the context only |
| Body field named like a placeholder | substituted into the path and removed from the payload | sent unchanged in the payload; never used for the URL |
| Placeholder supplied by neither | `PathResolutionException` | `PathResolutionException`, before the HTTP request |

In v8.x, `YamlConfigAdapter` substituted matching body fields into the path when it built the action. In v9.0 it keeps the configured path and body as they are, and `AbstractAction::getPath()` resolves every placeholder from the context at send time.

## Am I affected?

Look for actions whose YAML `path:` contains a `{placeholder}` and that also declare a `body:`. For each one, check whether callers pass the placeholder value inside the body instead of the context. After upgrading, such a call fails before any request is sent with:

```text
Missing path parameter "id" for path "/employees/{id}".
```

## Migrating

Move the URL value from the body to the context.

### Before (v8.x)

```php
$engine->send(
    actionName: UpdateEmployeeAction::getName(),
    body: UpdateEmployeeBody::create(['id' => 42, 'name' => 'New Name']),
);
// → PUT /employees/42, body {"name": "New Name"}
```

### After (v9.0)

```php
use IntegrationEngine\Core\Contract\Action\DefaultActionContext;

$engine->send(
    actionName: UpdateEmployeeAction::getName(),
    context: DefaultActionContext::create(['id' => 42]),
    body: UpdateEmployeeBody::create(['name' => 'New Name']),
);
// → PUT /employees/42, body {"name": "New Name"}
```

If the API also requires the value in the payload, keep it in the body as well. v9.0 sends it unchanged, where v8.x silently stripped it:

```php
$engine->send(
    actionName: UpdateEmployeeAction::getName(),
    context: DefaultActionContext::create(['id' => 42]),
    body: UpdateEmployeeBody::create(['id' => 42, 'name' => 'New Name']),
);
// → PUT /employees/42, body {"id": 42, "name": "New Name"}
```

Batches migrate the same way: pass the value through `EngineRequest`'s `context:` argument.

```php
new EngineRequest(
    UpdateEmployeeAction::getName(),
    context: DefaultActionContext::create(['id' => 42]),
    body: UpdateEmployeeBody::create(['name' => 'New Name']),
);
```

## Side effects worth knowing

- **Lifecycle and profiler paths.** `RequestSent::path` and the profiler's path column show the action's raw path. For an action whose URL value came from the body, v8.x already showed the substituted value (`/employees/42`); v9.0 shows the template (`/employees/{id}`), the same as for any other action.
- **Response cache.** The response-cache key includes the context but not the body. In v8.x, a cacheable action whose URL value came from the body shared one cache entry for every value; once the value moves to the context, each value gets its own entry.

## Unchanged

- The YAML format and the `{placeholder}` syntax.
- `DefaultActionContext` and `PathResolvableContextInterface`: a custom context still receives the raw path and can return `null` to fall back to placeholder resolution.
- Non-scalar placeholder values and an empty path returned by a custom context still throw `PathResolutionException`.
- A placeholder repeated in one path resolves every occurrence from a single context value.

See [Context and path resolution](./getting-started/context-and-path.md) for the current contract and the [9.0.0 changelog entry](../CHANGELOG.md#900---2026-09-24).
