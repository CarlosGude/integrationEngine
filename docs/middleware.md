# Middleware and response caching

There are two middleware levels because they see different data:

| Level | Contract | Receives | Use it for |
|---|---|---|---|
| Client middleware | `AbstractClientMiddleware` | action, context and caller headers, **before** the adapter builds the HTTP request | caching, logging, auditing, policies based on action metadata |
| Request middleware | `RequestMiddlewareInterface` | the fully built `Request` (method, final URL, headers, body, encoding, timeout), right before transport | request signing (OAuth 1.0a, AWS SigV4), final-header changes |

Both are configured per integration as ordered lists of service IDs, outermost first. Only tagged services are accepted.

## Client middleware

Extend `AbstractClientMiddleware`. `process()` handles one request; `processMany()` handles a batch and, by default, passes it through unchanged — override it only when the concern must be batch-aware.

```php
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Client\AbstractClientMiddleware;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;

final class AuditMiddleware extends AbstractClientMiddleware
{
    public function process(
        AbstractAction $action,
        ?ActionContextInterface $context,
        ?RequestHeadersInterface $headers,
        callable $next,
    ): array {
        // before the request
        $response = $next($action, $context, $headers);
        // after the response

        return $response;
    }
}
```

```yaml
# config/services.yaml
App\Infrastructure\Http\AuditMiddleware:
    tags: [integration_engine.middleware]

# config/packages/integration_engine.yaml
integration_engine:
    integrations:
        my_api:
            middlewares:
                - App\Infrastructure\Http\AuditMiddleware
```

### Order

```text
CachingMiddleware (outermost, always present)
    → your middlewares, in declaration order
        → TracingMiddleware (only when the Symfony profiler is active)
            → HTTP adapter
```

The first middleware in the list sees the request first and the response last. `CachingMiddleware` can short-circuit the whole inner chain; because tracing sits inside caching, cache hits are not timed as HTTP calls (see [Debugging](./advanced/debugging.md)).

### Middleware that depends on the base URL

A middleware whose behavior or cache keys depend on the active base URL can implement `BaseUrlAwareMiddlewareInterface`. When a call overrides the base URL, those middlewares are cloned for the new URL; the others are reused as they are.

### Built-in `LoggingMiddleware`

`IntegrationEngine\Infrastructure\Middleware\LoggingMiddleware` is an optional client middleware: register and tag it like your own. It logs the action, the resolved path, the response status and duration, and on failure the exception class and message.

It does not redact anything. A resolved path can contain identifiers, and an upstream error message can contain the response body (see [Errors](./errors.md#requestresponseexception)). Use it only where your logging policy allows that, or replace it with a middleware that redacts.

## Response caching

When an action declares `cache_ttl` in its YAML, `CachingMiddleware` caches the **raw** client response (body, headers and status) before mapping:

1. On a hit, it returns the cached raw response and the mapper runs as usual.
2. On a miss, it calls the inner chain and stores a successful response for `cache_ttl` seconds.
3. In a batch, hits and misses are split first; only the misses reach the transport, so they are still dispatched concurrently. Failures are never cached.

The cache backend is the integration's `cache_service` ([configuration](./configuration.md)). Because the raw response is cached, a change to a mapper also applies to responses that were cached before the change.

The cache key is:

```text
ie_response_<integration>_<xxh128(JSON([action class, base URL, context, request headers]))>
```

Two consequences to keep in mind:

- **The body is not in the key.** Two calls with the same action, context, headers and base URL but different bodies share an entry. Do not set `cache_ttl` on actions whose response depends on the body.
- **Connection and credentials are not in the key, only the base URL.** Tenants that share a base URL with different credentials can read each other's cached responses. Do not cache tenant-specific responses on a shared endpoint.

## Request middleware

Implement `RequestMiddlewareInterface`. The `Request` is immutable: `withHeader()` returns a new one.

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
# config/services.yaml
App\Infrastructure\Http\SigningMiddleware:
    tags: [integration_engine.request_middleware]

# config/packages/integration_engine.yaml
integration_engine:
    integrations:
        my_api:
            request_middlewares:
                - App\Infrastructure\Http\SigningMiddleware
```

Calling `$next` continues the chain and lets the middleware observe or wrap the response on the way back. Not calling it short-circuits the request: throw to reject it, or return a raw response (`body`, `headers`, optional `statusCode`).

Request middleware runs inside the built-in `rest`, `graphql` and `form_encoded` clients. A `client_service` builds its own requests and must provide equivalent behavior itself.

Configuring a request middleware makes `sendMany()` run sequentially for that integration — see [Batch requests](./getting-started/batch-requests.md#concurrency) for why.
