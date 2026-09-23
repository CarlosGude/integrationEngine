# Authorization

Authorization is declared per action. IntegrationEngine supports static credentials and dynamic token acquisition; runtime connection resolution can override either for a specific call.

## Static authorization

```yaml
GetOrders:
    action: App\...\GetOrdersAction
    method: GET
    path: /orders
    authorization:
        type: bearer
        token: '%env(MY_API_TOKEN)%'
```

Supported static forms:

| Type | Fields | Result |
|---|---|---|
| `bearer` | `token`, optional `prefix` | `Authorization: <prefix> <token>`; prefix defaults to `Bearer` |
| `basic` | `username`, `password` | HTTP Basic `Authorization` header |
| `api_key` | `token`, optional `header`, optional `prefix` | Custom header; header defaults to `X-Api-Key` |

```yaml
# Basic
authorization:
    type: basic
    username: '%env(API_USER)%'
    password: '%env(API_PASS)%'

# API key in a provider-specific header
authorization:
    type: api_key
    token: '%env(API_KEY)%'
    header: X-Shopify-Access-Token
```

## Dynamic authorization

Use `type: dynamic` when a second action must obtain the credential:

```yaml
FetchToken:
    action: App\...\FetchTokenAction
    method: POST
    path: /oauth/token

GetOrders:
    action: App\...\GetOrdersAction
    method: GET
    path: /orders
    authorization:
        type: dynamic
        action: FetchToken
        token_field: access_token
        ttl: 3600
        header: Authorization       # optional
        prefix: Bearer              # optional
```

The token action is a normal action. Its mapped response must expose `token_field` through `toArray()`.

On a cache miss the engine executes the token action, extracts the scalar token, stores it for `ttl`, converts the dynamic configuration to static authorization and sends the protected request.

### Rejected cached tokens

A 401 is retried once only when the rejected token came from cache:

1. remove the cached token;
2. fetch and cache a fresh token;
3. retry the protected request once.

A 401 returned after a freshly fetched token is not retried, and non-401 failures do not evict the token.

## Token cache identity

Dynamic token keys have this logical shape:

```text
integration_engine.token.{integration}.{token_action}.{xxh128(discriminator)}
```

The PSR-6 adapter sanitizes characters that are not portable cache-key characters before accessing the configured pool.

For runtime connections, the discriminator is selected in this order:

1. resolved `ConnectionCredentials::connectionId`;
2. the caller's scalar `connection` value;
3. resolved `baseUrl`;
4. an empty string when no connection-specific discriminator exists.

Use a stable, non-secret `connectionId` when several tenants can share the same base URL. Never use an API key, token or client secret as a connection ID.

## Cache backend

The bundle's default `CachePort` is a `Psr6CacheAdapter` over Symfony's `cache.app`. The actual storage and sharing semantics therefore depend on the application's Symfony Cache configuration; IntegrationEngine does not assume that `cache.app` is local, filesystem-backed or shared.

Override the pool per integration when token storage needs a dedicated backend:

```yaml
# config/packages/integration_engine.yaml
integration_engine:
    integrations:
        my_api:
            cache_service: cache.my_api_tokens
```

The configured service must satisfy the cache contract expected by the bundle wiring.

## Runtime connection overrides

A configured `connection_resolver` can return `ConnectionCredentials` with a different `baseUrl`, `authorization`, and optional `connectionId` for each call. The override is resolved before dynamic authorization, so per-connection credentials and token caches stay separated. See [HTTP clients — runtime connection resolution](../advanced/architecture/clients.md#runtime-connection-resolution).
