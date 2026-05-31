# Contributing & Development

## Requirements

- PHP 8.2+
- Composer
- Docker + Docker Compose (recommended)

## Setup

```bash
git clone https://github.com/YOUR_USERNAME/integration-engine.git
cd integration-engine
composer install
```

## Running tests

```bash
vendor/bin/phpunit --testdox
```

## Code quality

```bash
vendor/bin/phpstan analyse src tests --level=8
vendor/bin/php-cs-fixer fix --diff
```
