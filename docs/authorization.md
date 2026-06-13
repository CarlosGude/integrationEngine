# Authorization

Authorization is declared per action in the YAML. The engine handles header injection,
token fetching, caching, and 401 retries automatically — no manual token management.

---

## The minimum — static bearer token

Add an `authorization` block to any action entry:

```yaml
GetOrders:
    action: App\...\GetOrdersAction
    method: GET
    path:   /orders
    authorization:
        type:  bearer
        token: '%env(MY_API_TOKEN)%'
```

The engine injects `Authorization: Bearer <token>` on every request. That's all.

---

## All static auth types

| Type | Required fields | Header produced |
|---|---|---|
| `bearer` | `token` | `Authorization: Bearer {token}` |
| `bearer` + `prefix` | `token`, `prefix` | `Authorization: {prefix} {token}` |
| `basic` | `username`, `password` | `Authorization: Basic {base64(user:pass)}` |
| `api_key` | `token` | `X-Api-Key: {token}` |
| `api_key` + `header` | `token`, `header` | `{header}: {token}` |
| `api_key` + `header` + `prefix` | `token`, `header`, `prefix` | `{header}: {prefix} {token}` |

```yaml
# Bearer with custom prefix
authorization:
    type:   bearer
    token:  '%env(TOKEN)%'
    prefix: Token          # → Authorization: Token <value>

# Basic auth
authorization:
    type:     basic
    username: '%env(API_USER)%'
    password: '%env(API_PASS)%'

# API key in custom header
authorization:
    type:   api_key
    token:  '%env(API_KEY)%'
    header: X-Api-Key
    prefix: ''             # omit prefix entirely
```

---

## Dynamic auth — OAuth 2.0, session tokens

Use `type: dynamic` when the API requires a token obtained from a separate auth endpoint.
The engine calls the token action, caches the result, and injects it as static auth on
every protected action — no manual token management:

```yaml
FetchToken:
    action: App\...\FetchTokenAction
    method: POST
    path:   /oauth/token

GetOrders:
    action: App\...\GetOrdersAction
    method: GET
    path:   /orders
    authorization:
        type:         dynamic
        action:       FetchToken       # calls this action to obtain the token
        token_field:  access_token     # field in the token response toArray()
        ttl:          3600             # cache duration in seconds
        header:       Authorization    # optional — defaults to Authorization
        prefix:       Bearer           # optional — defaults to Bearer for Authorization header
```

The token action is a regular action — it needs its own `Action`, `Mapper`, and
`Response`. The response `toArray()` must expose the field named in `token_field`:

```php
use IntegrationEngine\Core\Contract\ResponseInterface;

final readonly class FetchTokenResponse implements ResponseInterface
{
    public function __construct(public readonly string $accessToken) {}

    public function toArray(): array
    {
        return ['access_token' => $this->accessToken]; // must match token_field
    }
}
```

---

## How caching works

The engine caches the token under the key:

```
integration_engine.token.{integrationName}.{authActionName}
```

On subsequent calls within the TTL, the cached token is used directly — the auth action
is not called again.

**401 retry:** If the API rejects a *cached* token with HTTP 401 (revoked or expired
server-side before its TTL), the engine:

1. Deletes the cache entry
2. Fetches a fresh token
3. Retries the original request **once**

A freshly fetched token rejected with 401 is **not** retried. Non-401 errors never evict
the cache.

---

## Cache backend

The default cache is `cache.app`, which is process-local under PHP-FPM (filesystem or
APCu). Each worker fetches its own token on first warm-up — with N workers, the token
endpoint is called up to N times per TTL window. For most APIs this is acceptable.

For APIs with strict rate limits on the token endpoint, configure a shared Redis pool:

```yaml
# config/packages/integration_engine.yaml
integration_engine:
    integrations:
        my_api:
            cache_service: 'cache.my_api_tokens'

# config/packages/cache.yaml
framework:
    cache:
        pools:
            cache.my_api_tokens:
                adapter:  cache.adapter.redis
                provider: 'redis://localhost'
```
