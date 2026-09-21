# v7.0 Client Registration Migration Guide

## Breaking Change: Client Service Registration

In v7.0, the client registration model changed from **shared global clients** to **per-integration clients**.

### What Changed

#### ❌ v6.0 (Old Model)

Global clients registered by adapter type:
```yaml
# In bundle services.yaml
integration_engine.client.rest:        # ← Available globally
integration_engine.client.graphql:     # ← Available globally
integration_engine.client.soap:        # ← etc.
```

**Demo app config:**
```yaml
countries:
    client_service: integration_engine.client.graphql  # Reference global service
    config_path: '%kernel.project_dir%/src/Integrations/Countries/Countries.yaml'
```

#### ✅ v7.0 (New Model)

Clients are registered **per integration** via the CompilerPass:
```php
// IntegrationCompilerPass::buildMiddlewareClient()
$clientId = "integration_engine.client.{$name}";  // e.g., "integration_engine.client.countries"
```

Each integration gets its own client with consistent middleware stack (caching, tracing, user middlewares).

### Migration Path

#### For v6.0 apps using `client_service`

**Old:**
```yaml
# config/packages/integration_engine.yaml
countries:
    client_service: integration_engine.client.graphql  # ❌ No longer exists
    config_path: '%kernel.project_dir%/src/Integrations/Countries/Countries.yaml'
```

**New:**
```yaml
# config/packages/integration_engine.yaml
countries:
    client: graphql  # ✅ Use the adapter name instead
    base_url: 'https://countries.trevorblades.com'
    config_path: '%kernel.project_dir%/src/Integrations/Countries/Countries.yaml'
```

### Why This Matters

1. **Simpler Configuration** — no need to reference service names; use the adapter name directly
2. **Consistent Middleware** — every integration's client gets the same middleware chain (caching, user middlewares, tracing)
3. **Better Isolation** — each integration is independent; no shared state between clients
4. **Easier Testing** — per-integration clients are simpler to mock/stub

### Available Adapters

Built-in adapters that can be used with `client:`:

- `rest` — SymfonyHttpClientAdapter (default, assumed if omitted)
- `graphql` — GraphQLClientAdapter

Custom adapters can be registered by tagging with `integration_engine.client_adapter`:

```yaml
# config/services.yaml
App\Infrastructure\Http\SoapClientAdapter:
    tags:
        - { name: integration_engine.client_adapter }
```

Then use in config:
```yaml
integration_engine:
    integrations:
        my_soap_service:
            client: soap
            config_path: '%kernel.project_dir%/src/Integrations/MySoap/config.yaml'
```

### Full Example

**Before (v6.0):**
```yaml
integration_engine:
    integrations:
        countries:
            client_service: integration_engine.client.graphql
            config_path: '%kernel.project_dir%/src/Integrations/Countries/Countries.yaml'
        stripe:
            client_service: app.client.stripe  # custom service
            config_path: '%kernel.project_dir%/src/Integrations/Stripe/Stripe.yaml'
```

**After (v7.0):**
```yaml
integration_engine:
    integrations:
        countries:
            client: graphql  # Built-in adapter
            base_url: 'https://countries.trevorblades.com'
            config_path: '%kernel.project_dir%/src/Integrations/Countries/Countries.yaml'
        stripe:
            client_service: app.client.stripe  # Still supported for fully custom clients
            config_path: '%kernel.project_dir%/src/Integrations/Stripe/Stripe.yaml'
```

### Note on Custom Clients

If you have a fully custom HTTP client with its own logic, you can still use `client_service` to reference it directly:

```yaml
integration_engine:
    integrations:
        my_custom_api:
            client_service: app.my_custom_client  # Your own service
            config_path: '%kernel.project_dir%/src/Integrations/MyCustom/config.yaml'
```

But the recommended approach is to use `client:` with built-in or tagged adapters.

---

## FAQ

**Q: Can I still use `client_service`?**  
A: Yes, for fully custom clients. But use `client:` for standard REST/GraphQL/etc.

**Q: Where do I get per-integration client IDs for referencing?**  
A: Use `integration_engine.client.{integration_name}`. For example:
- `integration_engine.client.countries` for the `countries` integration
- `integration_engine.client.stripe` for the `stripe` integration

These are auto-registered by the CompilerPass and available in your services for injection if needed.

**Q: What about middleware?**  
A: Middleware configuration remains unchanged. User middlewares are applied to each integration's client automatically.

```yaml
integration_engine:
    integrations:
        countries:
            client: graphql
            config_path: '%kernel.project_dir%/src/Integrations/Countries/Countries.yaml'
            middlewares:
                - app.middleware.rate_limit
```
