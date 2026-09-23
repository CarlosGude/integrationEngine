# HTTP clients and transport wiring

IntegrationEngine separates the engine contract from the HTTP implementation. `ClientInterface` is the minimum capability; optional interfaces add batching and runtime URL overrides.

For the architectural boundary around clients and middleware, see [ARCHITECTURE.md](../../ARCHITECTURE.md). This page is the configuration and extension reference.

## Built-in clients

Select the transport at integration level:

```yaml
integration_engine:
    integrations:
        my_api:
            base_url: 'https://api.example.com'
            config_path: '%kernel.project_dir%/config/integrations/my_api.yaml'
            client: rest
```

`client` defaults to `rest`.

| `client` | Adapter | Body/request model | Batch | Runtime `baseUrl` |
|---|---|---|---|---|
| `rest` | `SymfonyHttpClientAdapter` | ordinary HTTP, JSON by default | yes | yes |
| `graphql` | `GraphQLClientAdapter` | POST to the configured endpoint using `GraphQLBodyInterface` | yes | yes |
| `form_encoded` | `FormEncodedClientAdapter` | `application/x-www-form-urlencoded` | yes | yes |

All three built-ins implement `BatchClientInterface` and `DynamicBaseUrlClientInterface`. With `request_middlewares` configured, their batch path falls back to sequential per-item dispatch so middleware semantics remain identical to `send()`.

### REST

REST is the default. Action method and path are used to build the request. Bodies are JSON unless the action implements the form-encoding contract described below.

### GraphQL

Configure the endpoint as the integration `base_url` and implement `GraphQLBodyInterface`:

```php
use IntegrationEngine\Core\Contract\Action\GraphQLBodyInterface;

final readonly class GetUserBody implements GraphQLBodyInterface
{
    public function __construct(private int $id) {}

    public function getQuery(): string
    {
        return 'query GetUser($id: ID!) { user(id: $id) { id name } }';
    }

    public function getVariables(): array
    {
        return ['id' => $this->id];
    }

    public function toArray(): array
    {
        return ['query' => $this->getQuery(), 'variables' => $this->getVariables()];
    }
}
```

The GraphQL adapter posts to the integration endpoint, returns the `data` payload to the mapper and converts GraphQL `errors` into `RequestResponseException`.

### Form-encoded requests

Use `client: form_encoded` when an integration is form-based by default. `FormEncodedClientAdapter` delegates transport behavior to the REST adapter with form body encoding.

For a REST integration where only particular actions are form-encoded, implement `FormEncodedBodyInterface` on those action bodies instead of changing the whole integration client.

## `client` vs `client_service`

`client_service` bypasses the built-in adapter construction and injects an application service implementing `ClientInterface`:

```yaml
integration_engine:
    integrations:
        my_api:
            config_path: '%kernel.project_dir%/config/integrations/my_api.yaml'
            client_service: App\Infrastructure\Http\MyApiClient
```

`client` and `client_service` are alternative extension points. With `client_service`, the bundle does not control the transport, so transport options such as `retry`, `timeout`, `max_duration` and `block_private_networks` are rejected by configuration. Request middleware is likewise the responsibility of the custom client.

A custom service can opt into additional engine capabilities by implementing `BatchClientInterface` and/or `DynamicBaseUrlClientInterface`.

## Custom adapter type

Use `ClientAdapterInterface` when you want a reusable protocol type selectable through `client:`. Tag the service with `integration_engine.client_adapter`; the adapter's `getClientType()` becomes the configuration value.

```php
use IntegrationEngine\Core\Contract\Client\ClientAdapterInterface;

final class SoapClientAdapter implements ClientAdapterInterface
{
    public static function getClientType(): string
    {
        return 'soap';
    }

    public static function requiresPath(): bool
    {
        return false;
    }

    public static function requiresMethod(): bool
    {
        return false;
    }

    // ClientInterface::send() implementation omitted.
}
```

```yaml
App\Infrastructure\Http\SoapClientAdapter:
    tags: [integration_engine.client_adapter]
```

An application adapter registered for an existing type can replace the bundle adapter for that type.

## Runtime base URL

The `baseUrl` argument on `send()` and `EngineRequest` is honored only when the resolved client implements `DynamicBaseUrlClientInterface`:

```php
$response = $engine->send(
    GetOrdersAction::getName(),
    context: $context,
    baseUrl: $tenant->apiBaseUrl(),
);
```

The three built-ins support this. A custom client that does not implement the interface keeps its configured endpoint; the override is ignored.

In a batch, the engine groups prepared requests by resolved base URL before delegating each group to the client.

## Runtime connection resolution

Use `connection_resolver` when a per-call connection changes credentials as well as, or instead of, the URL:

```yaml
integration_engine:
    integrations:
        my_api:
            base_url: 'https://api.example.com'
            config_path: '%kernel.project_dir%/config/integrations/my_api.yaml'
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
            authorization: $tenant->authorization,
            connectionId: (string) $connection,
        );
    }
}
```

```php
$response = $engine->send(GetOrdersAction::getName(), connection: $tenantId);
```

`ConnectionCredentials` can provide `baseUrl`, `authorization` and `connectionId`; every field is optional. Passing a connection without a configured resolver is an error. Omitting `connection` leaves the integration's static configuration unchanged.

`connectionId` is also the strongest discriminator for dynamic-auth token caching. Use a stable, non-secret identifier when different connections may share one endpoint.

## Engine middleware vs request middleware

These extension points run at different levels.

`middlewares:` registers `AbstractClientMiddleware` services. They see the action, context and caller headers before a final transport request is built. This is appropriate for engine-level cross-cutting behavior.

`request_middlewares:` registers `RequestMiddlewareInterface` services. They receive an immutable `Request` containing the resolved method, URL, headers, body, encoding and optional timeout immediately before transport execution. This is the extension point for signatures such as OAuth 1.0a or AWS SigV4.

```php
use IntegrationEngine\Core\Contract\Client\Request;
use IntegrationEngine\Core\Contract\Client\RequestMiddlewareInterface;

final class SigningMiddleware implements RequestMiddlewareInterface
{
    public function handle(Request $request, callable $next): array
    {
        return $next($request->withHeader('Authorization', $this->sign($request)));
    }
}
```

```yaml
integration_engine:
    integrations:
        my_api:
            request_middlewares:
                - App\Infrastructure\Integrations\MyApi\SigningMiddleware
```

The built-in REST, GraphQL and form-encoded clients support request middleware. A `client_service` owns its request construction and must implement equivalent behavior itself if needed.

## Transport controls for built-in clients

The bundle can wrap its managed Symfony HTTP transport with:

- `timeout` and `max_duration`;
- private-network blocking;
- an `allowed_hosts` policy;
- retry configuration for selected status codes and methods.

These settings are integration-level controls. An action may additionally define its own `timeout`, which travels on the prepared request. See [Resilience and transport policy](../resilience.md) and [Security v8](../../security-v8.md) for the specialized rules rather than duplicating them here.
