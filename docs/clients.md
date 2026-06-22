# HTTP Clients

The client executes the HTTP request and returns the raw response array. Two built-in
adapters are included; you can add your own.

---

## The minimum — REST (default)

No configuration needed. When `client:` and `client_service:` are both absent, the
engine uses `SymfonyHttpClientAdapter` (REST):

```yaml
integration_engine:
    integrations:
        my_api:
            base_url: 'https://api.example.com'
            config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/MyApi/MyApi.yaml'
```

Standard REST semantics: JSON body serialization, status-code error handling, streaming
response consumption.

---

## GraphQL

Set `client: graphql` on the integration and implement `GraphQLBodyInterface` for each
action that sends a query:

```yaml
my_graphql_api:
    base_url:    'https://api.example.com/graphql'
    config_path: '...'
    client:      graphql
```

```php
use IntegrationEngine\Core\Contract\GraphQLBodyInterface;

final class GetUserBody implements GraphQLBodyInterface
{
    private function __construct(private int $id) {}

    public static function create(array $data): self { return new self((int) $data['id']); }

    public function getQuery(): string
    {
        return 'query GetUser($id: ID!) { user(id: $id) { id name email } }';
    }

    public function getVariables(): array { return ['id' => $this->id]; }
    public function toArray(): array { return ['query' => $this->getQuery(), 'variables' => $this->getVariables()]; }
}
```

`GraphQLClientAdapter` posts to `base_url` (ignoring the action path), extracts `data`
from the response, and throws `RequestResponseException` on `errors`.

> **Note:** The built-in GraphQL adapter sends requests sequentially in `sendMany()`.
> For real concurrency with GraphQL, see [Batch Requests — Concurrency](batch-requests.md#concurrency).

---

## `client:` vs `client_service:`

These are two different extension points:

| Option | What it does |
|---|---|
| `client: rest` / `client: graphql` | Selects a registered protocol adapter; the bundle handles wiring |
| `client_service: 'App\...\MyClient'` | Injects your service directly as `ClientInterface`; bypasses the adapter system |

The two are mutually exclusive. Use `client:` when you want the bundle to manage the
HTTP layer. Use `client_service:` when you need full control.

---

## Custom protocol adapter

Register a service with the `integration_engine.client_adapter` tag — the bundle
discovers it automatically. If `getClientType()` matches an existing adapter, yours takes
precedence:

```php
use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\ClientAdapterInterface;

final class SoapClientAdapter implements ClientAdapterInterface
{
    public static function getClientType(): string  { return 'soap'; }
    public static function requiresPath(): bool     { return false; }
    public static function requiresMethod(): bool   { return false; }

    public function send(AbstractAction $action, ...): array
    {
        // build SOAP envelope, execute, return decoded response as array
    }
}
```

```yaml
# services.yaml
App\Infrastructure\Http\SoapClientAdapter:
    tags:
        - { name: integration_engine.client_adapter }
```

```yaml
# integration_engine.yaml
my_soap_api:
    client: soap
```

---

## Custom service — full control

Use `client_service:` for retry logic, circuit breaking, custom logging, or test doubles:

```php
use IntegrationEngine\Core\Contract\ClientInterface;

final class RetryingHttpClient implements ClientInterface
{
    public function send(AbstractAction $action, ?ActionContextInterface $context = null, ...): array
    {
        // retry on 429, circuit break on 503, custom headers, etc.
    }
}
```

```yaml
my_api:
    client_service: 'App\Infrastructure\Http\RetryingHttpClient'
```

---

## Concurrency — `BatchClientInterface`

To get real concurrency in `sendMany()`, the client must implement `BatchClientInterface`.
`SymfonyHttpClientAdapter` does (dispatches all, then consumes); `GraphQLClientAdapter`
does not. A custom adapter or service can implement it regardless of protocol:

```php
use IntegrationEngine\Core\Contract\BatchClientInterface;
use IntegrationEngine\Core\Contract\ClientInterface;

final class ConcurrentGraphQLClient implements ClientInterface, BatchClientInterface
{
    public function send(...): array { ... }

    public function sendMany(array $requests): array
    {
        // Each PreparedRequest carries: action (static auth applied), context, caller headers.
        // 1. dispatch all — responses are lazy, requests run concurrently
        $handles = [];
        foreach ($requests as $key => $prepared) {
            $body = $prepared->action->getBody();
            $handles[$key] = $this->http->request('POST', $this->endpointUrl, [
                'json'    => $body?->toArray(),
                'headers' => $prepared->headers?->toArray() ?? [],
            ]);
        }

        // 2. consume — read only after all are in-flight
        $results = [];
        foreach ($handles as $key => $handle) {
            try {
                $results[$key] = $handle->toArray();
            } catch (\Throwable $e) {
                $results[$key] = $e;
            }
        }

        return $results;
    }
}
```

The engine detects `BatchClientInterface` at runtime and falls back to sequential sends
transparently when it is absent — no configuration, no error.

---

## Dynamic base URL per request — `DynamicBaseUrlClientInterface`

For integrations without one fixed base URL — e.g. an installable app where each
store/customer lives on its own domain — pass `baseUrl` to `send()`/`sendMany()` instead
of resolving a per-tenant client service yourself:

```php
$engine->send('get_orders', context: $context, baseUrl: $tenant->domain());
```

A client opts in by implementing:

```php
use IntegrationEngine\Core\Contract\Client\DynamicBaseUrlClientInterface;

interface DynamicBaseUrlClientInterface
{
    public function withBaseUrl(string $baseUrl): static;
}
```

| Client | Implements it? |
|---|---|
| `SymfonyHttpClientAdapter` | Yes — returns a new instance with `$baseUrl` swapped in |
| `GraphQLClientAdapter` | Yes — returns a new instance with `$endpointUrl` swapped in |
| Custom `ClientInterface` | Optional — if absent, an explicit `baseUrl` is silently ignored |

The engine checks `instanceof DynamicBaseUrlClientInterface` before calling
`withBaseUrl()`; clients that don't implement it keep using their configured URL with no
error. Omitting `baseUrl` entirely behaves exactly as before — this is purely additive.

In `sendMany()`, requests are grouped by their resolved `baseUrl` before dispatch, so a
batch mixing several target URLs still runs each group through `BatchClientInterface`
concurrently rather than falling back to sequential sends for the whole batch.

The bundle does not resolve or persist that URL — deciding *which* URL to pass (resolving
the active tenant/store) is the calling application's responsibility.
