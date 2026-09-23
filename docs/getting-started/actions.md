# Actions

An action is the static contract for one external operation: method, path, response mapper and optional body type. Runtime data is supplied separately, so action classes remain stateless.

## Define an action

Extend `AbstractAction` and implement the three static methods:

```php
use IntegrationEngine\Core\Contract\Action\AbstractAction;

final class GetEmployeeAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'GetEmployee';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): ?string
    {
        return GetEmployeeMapper::class;
    }
}
```

Then register the action in that integration's YAML file:

```yaml
GetEmployee:
    action: App\Infrastructure\Integrations\MyApi\GetEmployee\Request\GetEmployeeAction
    method: GET
    path: /employees/{id}
```

`getName()` must match the YAML key. `hasResponse(): false` does not skip the HTTP call; it skips mapper resolution and returns `EmptyResponse` after the request succeeds.

## Action YAML

The action file accepts these fields:

```yaml
ActionName:
    action: App\...\ActionClass        # required
    method: POST                       # optional, default POST
    path: /resource/{id}               # optional, default /
    body: App\...\ActionBody          # optional ActionBodyInterface class
    authorization:                     # optional; see authorization.md
        type: bearer
        token: '%env(API_TOKEN)%'
    cache_ttl: 60                      # optional raw-response cache TTL in seconds
    timeout: 5.0                       # optional per-action request timeout
```

Transport selection (`client`, `client_service`, retries, host policy and middleware) belongs in `config/packages/integration_engine.yaml`, not in the action YAML. See [HTTP clients](../advanced/architecture/clients.md).

## Runtime data

The configuration adapter creates action instances through `AbstractAction::create()`. Application code normally does not instantiate actions directly. Data that varies per call uses separate contracts:

| Runtime value | Contract |
|---|---|
| Path parameters and request context | `ActionContextInterface` |
| Request payload | `ActionBodyInterface` |
| Per-request headers | `RequestHeadersInterface` |
| Runtime endpoint override | `baseUrl` argument on `send()` / `EngineRequest` |
| Runtime connection/credentials | `connection` argument when a resolver is configured |

Body-backed path placeholders are resolved first by `YamlConfigAdapter` and removed from the outgoing body. Any remaining `{placeholder}` is resolved later from the action context. See [Context and path resolution](context-and-path.md).

## Invariants

Actions carry no mutable request state. A configured action may therefore be reused safely across single and batch dispatch without leaking values between calls. When `hasResponse()` is `true`, `mapper()` must identify the mapper for that action; the engine validates that relationship before mapping.
