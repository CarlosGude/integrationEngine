# Contributing & Development

## Requirements

- PHP 8.2+
- Composer

## Setup

```bash
git clone https://github.com/CarlosGude/integrationEngine.git
cd integrationEngine
composer install
```

## Running tests

```bash
vendor/bin/phpunit --testdox
```

Or via the Makefile:

```bash
make tests   # phpunit
make stan    # phpstan level 8
make cs      # php-cs-fixer (dry-run)
make qa      # all three in sequence
```

## Code quality

```bash
vendor/bin/phpstan analyse src tests --level=8
vendor/bin/php-cs-fixer fix --diff
```

## Further reading

- [`ARCHITECTURE.md`](./ARCHITECTURE.md) — design decisions: why actions are stateless,
  how path resolution works, the mapper invariant, cache behaviour, and the
  DTO/domain boundary.
- [`.agent/integration-engine-agent-guide.md`](agent/integration-engine-agent-guide.md) —
  AI agent context for automated integration generation. Not intended for human workflows.
