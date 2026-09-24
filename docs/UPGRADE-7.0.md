# Upgrade v6.0 → v7.0

v7.0 is a major release with breaking changes focused on **per-integration architecture** (clients, middleware, auth caching) and **request signing** (OAuth 1.0a, AWS SigV4).

**TL;DR:** Update `client_service:` to `client:`, rename middlewares to use tags and priority, implement connection resolver if using multi-tenant, add request middlewares for signing.

---

## Breaking Changes

### 1. Client Registration Model (BREAKING)

**In v6.0**, clients were global by type:
```yaml
integration_engine:
  integrations:
    my_api:
      client_service: integration_engine.client.graphql  # ❌ Global reference
```

**In v7.0**, each integration gets its own auto-wired client:
```yaml
integration_engine:
  integrations:
    my_api:
      client: graphql  # ✅ Auto-wired per-integration
```

**Migration guide:** See [MIGRATION-v7-client-registration.md](./MIGRATION-v7-client-registration.md).

---

### 2. Middleware Pipeline Refactor (BREAKING)

**In v6.0**, middleware was a decorator chain:
```php
// v6.0: Middleware as decorators
class TracingMiddleware extends AbstractClientMiddleware { }
class CachingMiddleware extends AbstractClientMiddleware { }
```

**In v7.0**, middleware uses Symfony tags with priority ordering:
```yaml
# services.yaml
App\Infrastructure\Http\RateLimitMiddleware:
  tags:
    - { name: integration_engine.middleware, priority: 10 }

# integration_engine.yaml
my_api:
  middlewares:
    - App\Infrastructure\Http\RateLimitMiddleware
```

**Layer order (outermost → innermost):**
1. `CachingMiddleware` (always first)
2. User middlewares (in declaration order)
3. `TracingMiddleware` (debug only)
4. HTTP adapter

**Why:** Better isolation, clearer priority control, simpler injection per integration.

---

### 3. Request Middleware (NEW)

For request signing schemes requiring the **fully-built request** (method, URL, headers, body) — OAuth 1.0a, AWS SigV4:

```php
use IntegrationEngine\Core\Contract\Client\Request;
use IntegrationEngine\Core\Contract\Client\RequestMiddlewareInterface;

final class OAuth1SigningMiddleware implements RequestMiddlewareInterface
{
    public function handle(Request $request, callable $next): array
    {
        $signature = $this->sign($request); // your OAuth 1.0a logic
        return $next($request->withHeader('Authorization', $signature));
    }
}
```

Register in config:
```yaml
# services.yaml
App\Infrastructure\Integrations\MyApi\OAuth1SigningMiddleware:
  tags: [integration_engine.request_middleware]

# integration_engine.yaml
my_api:
  request_middlewares:
    - App\Infrastructure\Integrations\MyApi\OAuth1SigningMiddleware
```

**Important:** Request middlewares degrade `sendMany()` to sequential execution (each request goes through its own chain). Use only when necessary.

---

### 4. Connection Resolver (NEW)

For multi-tenant integrations where each connection has different credentials or base URL:

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
            connectionId: (string) $connection,  // required if connections share base_url
        );
    }
}
```

Register in config:
```yaml
integration_engine:
  integrations:
    my_api:
      connection_resolver: App\Infrastructure\Integrations\MyApi\MyApiConnectionResolver
```

Call with connection:
```php
$engine->send('GetOrders', connection: $tenantId);
```

**Token caching:** Dynamic auth tokens are cached per `connectionId` (or `$connection` if scalar, or resolved `baseUrl`). Two connections never share one.

---

### 5. Path Resolution Uses Context Only

Move URL parameters previously supplied by the body into `context`:

```php
$engine->send('UpdateEmployee',
  context: DefaultActionContext::create(['id' => 42]),
  body: UpdateEmployeeBody::create(['name' => 'Ada'])
);
// → PUT /employees/42, body { "name": "Ada" }
```

Use `IntegrationEngine\Core\Contract\Action\DefaultActionContext` or a custom context.
Body fields are preserved, including fields whose names match URL placeholders.
If a URL parameter is absent from context, path resolution fails even when the body contains it.

---

## Non-Breaking Changes

### New: FormEncodedClientAdapter

For integrations that send `application/x-www-form-urlencoded` bodies:

```yaml
integration_engine:
  integrations:
    my_api:
      client: form_encoded
```

---

### Improved: PHP 8.4 Compatibility

- Readonly properties use class-level `readonly` (PHP 8.2+) or per-property
- PHPStan level=max passes (0 errors)
- All 601 tests passing on PHP 8.2, 8.3, 8.4

---

## Migration Checklist

- [ ] Update `client_service:` → `client:` in `integration_engine.yaml`
- [ ] Verify custom clients still implement `ClientAdapterInterface`
- [ ] If using custom middleware, tag them `integration_engine.middleware`
- [ ] If signing requests (OAuth 1.0a, AWS SigV4), implement `RequestMiddlewareInterface`
- [ ] If multi-tenant, implement `ConnectionResolverInterface`
- [ ] Test dynamic auth token caching with multiple connections
- [ ] Run your test suite — adapter interfaces are the same, behavior is identical

---

## No Deprecation Path

This is a major release. **No backwards compatibility shims** for `client_service:` — you must update your config.

**Why:** Simpler codebase, clearer per-integration contracts, better isolation.

---

## Further Reading

- [MIGRATION-v7-client-registration.md](./MIGRATION-v7-client-registration.md) — detailed client migration
- [DOCUMENTATION.md](./DOCUMENTATION.md) → *Middleware Pipeline* section
- [DOCUMENTATION.md](./DOCUMENTATION.md) → *Connection Resolver* section
- [DOCUMENTATION.md](./DOCUMENTATION.md) → *Request Middleware* section
- [CHANGELOG.md](../CHANGELOG.md) → v7.0.0 and v7.0.1 entries
