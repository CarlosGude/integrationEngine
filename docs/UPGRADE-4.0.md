# Upgrading from v3.x to v4.0

This guide covers the breaking changes in v4.0 and how to migrate your code.

## Overview of v4.0 Breaking Changes

v4.0 replaces the middleware decorator pattern with a unified pipeline architecture, introduces request middleware support, and adds per-connection resolution for multi-tenant scenarios.

## Breaking Changes

### 1. Middleware Architecture: Decorator → Pipeline

**v3.x (Decorator Pattern)**

Middlewares were composed as nested decorators:

```php
// In DI configuration
$decoratedClient = new MyMiddlewareA($originalClient);
$decoratedClient = new MyMiddlewareB($decoratedClient);
```

**v4.0 (Pipeline Pattern)**

Middlewares are discovered via tagging and applied in priority order by `MiddlewareClient`:

```php
// In services.yaml
MyMiddlewareA:
    tags:
        - { name: 'integration_engine.middleware', priority: 100 }

MyMiddlewareB:
    tags:
        - { name: 'integration_engine.middleware', priority: 50 }
```

**Upgrade Path:**

1. Change all custom middleware base classes:
   - From: `implements ClientMiddlewareInterface`
   - To: `extends AbstractClientMiddleware`

2. Remove any custom decorator wiring in DI configuration.

3. Tag each middleware with `integration_engine.middleware` (and optional `priority`; default is 0).

4. If a middleware needs batch awareness, override `processMany()`:

```php
class MyMiddleware extends AbstractClientMiddleware
{
    public function process(PreparedRequest $request, callable $next): Response
    {
        // Single request handling
        return $next($request);
    }

    public function processMany(array $requests, callable $next): array
    {
        // Batch handling (default: sequential passthrough)
        return $next($requests);
    }
}
```

### 2. Interface Renamed: `BaseUrlAwareClientInterface` → `DynamicBaseUrlClientInterface`

**v3.x**

```php
interface BaseUrlAwareClientInterface extends ClientInterface
{
    // ...
}
```

**v4.0**

```php
interface DynamicBaseUrlClientInterface extends ClientInterface
{
    // Supports per-request base URL override in send()/sendMany()
}
```

**Upgrade Path:**

Update any custom client implementations to implement `DynamicBaseUrlClientInterface` instead.

### 3. Connection Resolver (New Feature)

For multi-tenant scenarios, v4.0 introduces connection-aware base URL and authorization.

**New in v4.0:**

```php
// In your app's services.yaml
integration_engine:
    integrations:
        my_api:
            connection_resolver: 'app.integrations.my_api.connection_resolver'
```

Implement `ConnectionResolverInterface`:

```php
use IntegrationEngine\Core\Contract\Connection\ConnectionResolverInterface;
use IntegrationEngine\Core\Contract\Connection\ConnectionCredentials;

class MyConnectionResolver implements ConnectionResolverInterface
{
    public function resolve($connection): ConnectionCredentials
    {
        // $connection is opaque; resolve to credentials for your tenant
        $tenant = $this->tenantService->find($connection);
        
        return ConnectionCredentials::create()
            ->withBaseUrl($tenant->apiBaseUrl)
            ->withAuthorization($tenant->apiKey)
            ->withConnectionId((string)$tenant->id);
    }
}
```

Pass connection info to `send()` / `sendMany()`:

```php
$response = $integrationEngine->send(
    'GetUser',
    $context,
    $body,
    headers: [],
    baseUrl: null,
    connection: $tenantId  // Resolved by ConnectionResolverInterface
);
```

**If you don't use multi-tenancy**, don't implement this interface; it's entirely optional.

### 4. Request Middleware (New)

For signing schemes (OAuth 1.0a, Hawk, etc.) that need the *fully-built* request, use `RequestMiddlewareInterface`:

**v4.0 (New):**

```php
use IntegrationEngine\Core\Contract\Client\RequestMiddlewareInterface;
use IntegrationEngine\Core\Contract\Client\Request;

class OAuth1SignerRequestMiddleware implements RequestMiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        // Sign the fully-built request
        $signed = $this->signer->sign($request);
        
        return $next($signed);
    }
}
```

Declare in integration config:

```yaml
integrations:
    my_api:
        request_middlewares:
            - 'app.middleware.oauth1_signer'
```

**If you were implementing OAuth 1.0a or similar in v3.x**, migrate to `RequestMiddlewareInterface` for cleaner separation.

## Migration Checklist

- [ ] Update all custom middleware to extend `AbstractClientMiddleware`.
- [ ] Add `integration_engine.middleware` tags to each middleware.
- [ ] Remove manual decorator wiring from DI configuration.
- [ ] Update any `BaseUrlAwareClientInterface` references to `DynamicBaseUrlClientInterface`.
- [ ] If multi-tenant, implement and register `ConnectionResolverInterface`.
- [ ] If using custom request signing, migrate to `RequestMiddlewareInterface`.
- [ ] Run `make test` and `make stan` to verify.
- [ ] Review `CHANGELOG.md` for v4.0.0 for additional details.

## Support

For questions or issues during upgrade, consult:

- Architecture documentation: [`ARCHITECTURE.md`](./ARCHITECTURE.md)
- Middleware and clients: [`docs/advanced/architecture/clients.md`](./advanced/architecture/clients.md)
- GitHub discussions: [IntegrationEngine discussions](https://github.com/carlosgude/integrationengine/discussions)
