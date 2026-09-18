# HTTP Clients

The client executes the HTTP request and returns `array{body: array, headers:
array<string, string[]>}` — the decoded body plus the response's HTTP headers,
which the engine hands to your mapper as separate arguments. Two built-in
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
use IntegrationEngine\Core\Contract\Action\GraphQLBodyInterface;

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
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Client\ClientAdapterInterface;

final class SoapClientAdapter implements ClientAdapterInterface
{
    public static function getClientType(): string  { return 'soap'; }
    public static function requiresPath(): bool     { return false; }
    public static function requiresMethod(): bool   { return false; }

    public function send(AbstractAction $action, ...): array
    {
        // build SOAP envelope, execute, decode the response, and return
        // both the body and the response headers:
        return ['body' => $decoded, 'headers' => $responseHeaders];
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
use IntegrationEngine\Core\Contract\Client\ClientInterface;

final class RetryingHttpClient implements ClientInterface
{
    public function send(AbstractAction $action, ?ActionContextInterface $context = null, ...): array
    {
        // retry on 429, circuit break on 503, custom headers, etc.
        return ['body' => $decoded, 'headers' => $responseHeaders];
    }
}
```

`request_middlewares:` (see below) is not available here — a `client_service`
builds its own requests, so it's responsible for any request-level logic
(including full-request signing) itself.

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
use IntegrationEngine\Core\Contract\Client\BatchClientInterface;
use IntegrationEngine\Core\Contract\Client\ClientInterface;

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
                $results[$key] = ['body' => $handle->toArray(), 'headers' => $handle->getHeaders(false)];
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

---

## Runtime connection resolution — `ConnectionResolverInterface`

`baseUrl` above only swaps the target URL. When a connection also needs
different credentials — one integration serving several tenants, each with
its own API key — configure a resolver instead:

```yaml
integration_engine:
    integrations:
        my_api:
            base_url: 'https://api.example.com'   # fallback
            connection_resolver: App\Infrastructure\Integrations\MyApi\MyApiConnectionResolver
```

```php
use IntegrationEngine\Core\Contract\Connection\ConnectionCredentials;
use IntegrationEngine\Core\Contract\Connection\ConnectionResolverInterface;

final class MyApiConnectionResolver implements ConnectionResolverInterface
{
    public function resolve(mixed $connection): ConnectionCredentials
    {
        $tenant = $this->tenants->getById($connection);

        return new ConnectionCredentials(
            baseUrl: $tenant->baseUrl,
            authorization: new StaticAuthorizationConfig('bearer', ['token' => $tenant->apiKey]),
            connectionId: (string) $connection,
        );
    }
}
```

```php
$engine->send('get_orders', connection: $tenantId);
```

`ConnectionCredentials { ?baseUrl, ?authorization, ?connectionId }` — every
field optional; only set what actually varies per connection. `$connection`
is opaque to the engine; your resolver decides what it means. Omitting
`connection` never touches the resolver, so existing single-connection
integrations are unaffected; passing it without a `connection_resolver`
configured throws `ConnectionResolutionException`.

**Dynamic-auth token cache:** if the action uses dynamic authorization and
several connections could share one `base_url`, set `connectionId` to a
stable, non-secret identifier (never the API key/secret) — otherwise those
connections would collide on the same cached token. If every connection has
its own `base_url`, the engine already discriminates by that and
`connectionId` is optional.

---

## Request middleware — full-request signing

For signature schemes that need the complete outgoing request (method,
resolved URL, headers, body) rather than a static credential — OAuth 1.0a,
AWS SigV4 — implement `RequestMiddlewareInterface`:

```php
use IntegrationEngine\Core\Contract\Client\Request;
use IntegrationEngine\Core\Contract\Client\RequestMiddlewareInterface;

final class OAuth1SigningMiddleware implements RequestMiddlewareInterface
{
    public function handle(Request $request, callable $next): array
    {
        $signature = $this->sign($request); // your signing logic

        return $next($request->withHeader('Authorization', $signature));
    }
}
```

```yaml
# services.yaml
App\Infrastructure\Integrations\MyApi\OAuth1SigningMiddleware:
    tags: [integration_engine.request_middleware]
```

```yaml
# integration_engine.yaml
my_api:
    request_middlewares:
        - App\Infrastructure\Integrations\MyApi\OAuth1SigningMiddleware
```

`Request { method, url, headers, ?body }` is the fully-resolved request —
everything a signature could need is already present. `$next` continues the
chain (optionally with a modified `$request`); not calling it rejects the
request (throw) or short-circuits with a canned result. Multiple
middlewares run outermost-first, same convention as `middlewares:`.

Only the built-in REST/GraphQL adapters support this — see the note in
"Custom service — full control" above. Configuring any `request_middlewares`
for an integration makes its `sendMany()` dispatch sequentially instead of
concurrently, since each item's chain may need to observe its own response.
