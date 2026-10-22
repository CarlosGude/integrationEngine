# HTTP clients and transport wiring

IntegrationEngine separates the engine contract from the HTTP implementation. `ClientInterface` is the minimum capability; optional interfaces add batching and runtime URL overrides.

| Capability | Contract | Effect |
|---|---|---|
| Send | `ClientInterface` | Executes one action and returns the decoded body, response headers and, when known, the status code. |
| Batch | `BatchClientInterface` | Executes several prepared requests concurrently ([Batch requests](../../getting-started/batch-requests.md)). |
| Runtime base URL | `DynamicBaseUrlClientInterface` | Returns a copy of the client pointed at a per-call base URL. |
| Named adapter | `ClientAdapterInterface` | Makes the client selectable through `client:`. |

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

`client` defaults to `rest`. The three built-ins implement `BatchClientInterface` and `DynamicBaseUrlClientInterface`, and support [request middleware](../../middleware.md#request-middleware).

| `client` | Adapter | Request model |
|---|---|---|
| `rest` | `SymfonyHttpClientAdapter` | ordinary HTTP, JSON body by default |
| `graphql` | `GraphQLClientAdapter` | POST to the configured endpoint with `GraphQLBodyInterface` |
| `form_encoded` | `FormEncodedClientAdapter` | `application/x-www-form-urlencoded` body by default |

### REST

The action's method and path build the request.

- Bodies are sent only for POST, PUT and PATCH. They are JSON unless the body implements `FormEncodedBodyInterface` ([Form bodies](../../form-bodies.md)).
- A per-action `timeout` is passed to Symfony HttpClient.
- HTTP 204 or an empty body is returned to the mapper as an empty array.
- HTTP status ≥ 400 and network errors throw `RequestResponseException` ([Errors](../../errors.md#requestresponseexception)).

### GraphQL

Configure the endpoint as the integration `base_url` and implement `GraphQLBodyInterface`:

```php
use IntegrationEngine\Core\Contract\Action\GraphQLBodyInterface;

final readonly class GetUserBody implements GraphQLBodyInterface
{
    private function __construct(private int $id) {}

    public static function create(array $data): self
    {
        return new self((int) ($data['id'] ?? 0));
    }

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

- The adapter always sends POST to the endpoint, with `query` and `variables` as JSON.
- The mapper receives only the `data` member of the response.
- A non-empty `errors` array throws `RequestResponseException`, even when the HTTP status is 200.
- A body that is not a `GraphQLBodyInterface` is rejected.
- The per-action `timeout` is **not** applied to GraphQL requests; use the integration-level `timeout` instead.

### Form-encoded

`client: form_encoded` makes form encoding the default for a whole integration; it delegates to the REST adapter. For a REST integration where only some actions are form-encoded, see [Form bodies](../../form-bodies.md).

## Header precedence

The built-in clients merge headers in this order, later ones winning:

1. adapter default: `Accept: application/json`;
2. the integration's `headers`;
3. the headers produced by the action's authorization;
4. the caller's `RequestHeadersInterface` headers.

## `client` vs `client_service`

`client_service` bypasses the built-in adapters and injects an application service implementing `ClientInterface`:

```yaml
integration_engine:
    integrations:
        my_api:
            config_path: '%kernel.project_dir%/config/integrations/my_api.yaml'
            client_service: App\Infrastructure\Http\MyApiClient
```

With `client_service` the bundle does not control the transport, so `retry`, `timeout`, `max_duration` and `block_private_networks` are rejected by configuration. The custom client also owns request construction: request middleware does not run, and batching and runtime base URLs work only if it implements `BatchClientInterface` and `DynamicBaseUrlClientInterface`.

For providers that serve CSV, a custom client can turn the text into rows with the standalone `IntegrationEngine\Utils\CsvParser::parse($csv, $options)`. `CsvParseOptions` sets the delimiter, enclosure, source encoding (converted to UTF-8; `null` keeps the bytes as they are), empty-line skipping and the header row; it has presets for semicolon (`semiocolnDelimited()`, spelled that way in the code), tab, UTF-16 and ISO-8859-1. Every row must have as many columns as the header.

## Custom adapter type

Use `ClientAdapterInterface` when you want a reusable protocol type selectable through `client:`. Tag the service with `integration_engine.client_adapter`; the adapter's `getClientType()` becomes the configuration value, and `requiresPath()` / `requiresMethod()` tell the generator which action fields to ask for.

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

An application adapter registered for an existing type replaces the bundle adapter for that type. Implement `BatchClientInterface` and `DynamicBaseUrlClientInterface` as well if it should support concurrent batches and runtime base URLs.

## Runtime base URL

The `baseUrl` argument on `send()` and `EngineRequest` is honored only when the client implements `DynamicBaseUrlClientInterface`:

```php
$response = $engine->send(
    GetOrdersAction::getName(),
    context: $context,
    baseUrl: $tenant->apiBaseUrl(),
);
```

The three built-ins support it. A custom client without the interface silently keeps its configured endpoint.

## Runtime connection resolution

Use `connection_resolver` when a per-call connection changes the credentials as well as, or instead of, the URL — typically one integration serving several tenants or accounts:

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

The engine never inspects `connection`; it is only passed to the resolver when it is not `null`. Every field of `ConnectionCredentials` is optional, so set only what varies per connection.

- An explicit `baseUrl` argument wins over the resolved one.
- A resolved `authorization` replaces the action's authorization from YAML.
- `connectionId` separates the dynamic-token cache between connections ([Authorization](../../getting-started/authorization.md#token-cache-identity)). It must be stable and non-secret: a tenant or account ID, never a token or password.
- Passing a `connection` to an integration without a resolver throws `ConnectionResolutionException`.

## Transport controls

For the built-in clients, the bundle builds the Symfony transport with optional timeouts, retries, a host allowlist and private-network blocking. See [Resilience](../../resilience.md) and [Security](../../security.md); the decorator order is in [Architecture](../../architecture.md#transport-composition).
