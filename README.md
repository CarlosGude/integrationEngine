# IntegrationEngine Bundle

A Symfony bundle for centralising external API integrations behind a consistent, hexagonal architecture.

## Requirements

- PHP 8.4+
- Symfony 7.x or 8.x

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
php bin/console make:integration RickAndMorty GetCharacters
```

**2. Fill in the generated files** in `src/Infrastructure/Integrations/RickAndMorty/GetCharacters/`:

- `Response/GetCharactersMapper.php` — map the raw API response to your DTO
- `Response/GetCharactersResponse.php` — define the response fields

For POST/PUT actions, also fill in:

- `Request/GetCharactersBody.php` — define the request fields in `toArray()`

**3. Register in `config/packages/integration_engine.yaml`:**

```yaml
integration_engine:
    integrations:
        rick_and_morty:
            config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/RickAndMorty/RickAndMorty.yaml'
            base_url: 'https://rickandmortyapi.com/api'
```

**4. Use it from any service:**

```php
use App\Infrastructure\Integrations\RickAndMorty\RickAndMortyIntegration;
use IntegrationEngine\Core\Registry\IntegrationRegistry;

final class CharacterService
{
    public function __construct(
        private readonly IntegrationRegistry $registry,
    ) {}

    public function getCharacters(): array
    {
        return $this->registry
            ->get(RickAndMortyIntegration::NAME)
            ->send('GetCharacters')
            ->toArray();
    }
}
```

## Configuration reference

```yaml
integration_engine:
  integrations:
    my_api:
      config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/MyApi/MyApi.yaml'

      # Option A — built-in HTTP client (recommended)
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