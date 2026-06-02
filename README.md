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
    IntegrationEngine\Bundle\IntegrationEngineBundle::class => ['all' => true],
];
```

## Quick start

### 1. Generate an integration

```bash
php bin/console make:integration Acme GetUsers
```

### 2. Use it from a service

```php
final class UserService
{
    public function __construct(
        private readonly IntegrationEngine\Core\Registry\IntegrationRegistry $registry,
    ) {}

    public function getUsers(): array
    {
        return $this->registry
            ->get('acme')
            ->send('GetUsers')
            ->toArray();
    }
}
```

## Usage patterns

### Simple GET

```php
->send('ListUsers')
```

### With body (POST/PUT)

```php
->send(
    actionName: 'CreateUser',
    body: CreateUserBody::create([
        'name' => 'Rick',
    ])
)
```

### With context (path/query parameters)

```php
->send(
    actionName: 'GetUser',
    context: GetUserContext::create([
        'id' => 1,
    ])
)
```

### Mixed (most common)

```php
->send(
    actionName: 'UpdateUser',
    body: UpdateUserBody::create([...]),
    context: UpdateUserContext::create(['id' => 1]),
)
```

## Configuration reference

```yaml
integration_engine:
  integrations:
    my_api:
      config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/MyApi/MyApi.yaml'
      base_url: '%env(MY_API_BASE_URL)%'
```

