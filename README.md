# IntegrationEngine Bundle

A Symfony bundle for centralising external API integrations behind a consistent, hexagonal architecture.

## Requirements

- PHP 8.2+
- Symfony 6.4, 7.x, or 8.x

## Installation

```bash
composer require carlosgude/integration-engine
```

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    IntegrationEngine\Bundle\IntegrationEngineBundle::class => ['all' => true],
];
```

## Quick start

**1. Generate an integration skeleton:**

```bash
php bin/console make:integration Stripe GetCharge
```

The command is interactive: it asks for the HTTP method and the URL path, then prepends the verb to the resource name automatically (`Get` + `Charge` → `GetCharge`).

**2. Fill in the generated files** in `src/Infrastructure/Integrations/Stripe/GetCharge/`:

- `Request/GetChargeAction.php` — already wired; no edits needed for a basic GET
- `Response/GetChargeMapper.php` — map the raw API response to your DTO
- `Response/GetChargeResponse.php` — define the response fields

For POST/PUT actions, also fill in:

- `Request/GetChargeBody.php` — define the request fields in `toArray()`

**3. Register in `config/packages/integration_engine.yaml`:**

```yaml
integration_engine:
    integrations:
        stripe:
            config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/Stripe/Stripe.yaml'
            base_url: '%env(STRIPE_BASE_URL)%'
```

**4. Use it from any service:**

```php
use App\Infrastructure\Integrations\Stripe\StripeIntegration;
use App\Infrastructure\Integrations\Stripe\GetCharge\Request\GetChargeBody;
use IntegrationEngine\Core\Registry\IntegrationRegistry;

final class OrderService
{
    public function __construct(
        private readonly IntegrationRegistry $registry,
    ) {}

    public function charge(string $token, int $amount): GetChargeResponse
    {
        return $this->registry
            ->get(StripeIntegration::NAME)
            ->send('GetCharge', new GetChargeBody($token, $amount));
    }
}
```

## Configuration reference

```yaml
integration_engine:
    integrations:
        my_api:
            config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/MyApi/MyApi.yaml'

            # Option A — built-in HTTP client (recommended for most cases)
            base_url: '%env(MY_API_BASE_URL)%'

            # Option B — custom ClientInterface implementation
            # client_service: App\Infrastructure\Integrations\MyApi\MyApiHttpClient

            # Optional — custom CachePort for persistent token caching (e.g. Redis)
            # Defaults to InMemoryCacheAdapter (process-scoped, not suitable for production dynamic auth)
            # cache_service: App\Cache\SymfonyCacheAdapter
```

> **Important:** `InMemoryCacheAdapter` is process-scoped. Under PHP-FPM each request starts a fresh process, so cached tokens are lost between requests. If you use dynamic authorization in production, provide a `cache_service` backed by Redis or APCu.

## License

MIT