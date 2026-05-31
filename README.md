# IntegrationEngine Bundle

A Symfony bundle for centralising external API integrations behind a consistent, hexagonal architecture.

## Requirements

- PHP 8.2+
- Symfony 6.4 or 7.x

## Installation

```bash
composer require vendor/integration-engine
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
php bin/console make:integration Stripe ChargeCard
```

**2. Fill in the generated files** in `src/Integration/Stripe/`:

- `Body/ChargeCardBody.php` — define the request fields
- `Mapper/ChargeCardMapper.php` — map the raw API response to your DTO
- `Response/ChargeCardResponse.php` — define the response fields
- `config/stripe.yaml` — set the correct HTTP method and path

**3. Register in `config/packages/integration_engine.yaml`:**

```yaml
integration_engine:
    integrations:
        stripe:
            config_path: '%kernel.project_dir%/src/Integration/Stripe/config/stripe.yaml'
            base_url: '%env(STRIPE_BASE_URL)%'
```

**4. Use it from any service:**

```php
use App\Integration\Stripe\Body\ChargeCardBody;
use App\Integration\Stripe\StripeIntegration;
use IntegrationEngine\Core\Registry\IntegrationRegistry;

final class OrderService
{
    public function __construct(
        private readonly IntegrationRegistry $registry,
    ) {}

    public function charge(string $token, int $amount): ChargeCardResponse
    {
        return $this->registry
            ->get(StripeIntegration::NAME)
            ->send('chargeCard', new ChargeCardBody($token, $amount));
    }
}
```

## Configuration reference

```yaml
integration_engine:
    integrations:
        my_api:
            config_path: '%kernel.project_dir%/src/Integration/MyApi/config/my_api.yaml'

            # Option A — built-in HTTP client (recommended for most cases)
            base_url: '%env(MY_API_BASE_URL)%'

            # Option B — custom ClientInterface implementation
            # client_service: App\Integration\MyApi\MyApiHttpClient

            # Optional — custom CachePort for persistent token caching (e.g. Redis)
            # Defaults to InMemoryCacheAdapter (process-scoped, not suitable for production dynamic auth)
            # cache_service: App\Cache\SymfonyCacheAdapter
```

> **Important:** `InMemoryCacheAdapter` is process-scoped. Under PHP-FPM each request starts a fresh process, so cached tokens are lost between requests. If you use dynamic authorization in production, provide a `cache_service` backed by Redis or APCu.

## License

MIT
