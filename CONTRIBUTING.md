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
make test   # phpunit
make stan   # phpstan level max
make cs     # php-cs-fixer (dry-run)
make qa     # cs + stan + test — run before each commit
make ci     # qa + mutation — run before opening a PR
```

## Code quality

Quality gates (style, static analysis, tests, mutation), their commands, and
current MSI: see [`docs/QUALITY.md`](docs/QUALITY.md).

## Further reading

- [`ARCHITECTURE.md`](./ARCHITECTURE.md) — design decisions: why actions are stateless,
  how path resolution works, the mapper invariant, cache behaviour, and the
  DTO/domain boundary.
- [`agent/integration-engine-agent-guide.md`](agent/integration-engine-agent-guide.md) —
  AI agent context for automated integration generation. Not intended for human workflows.
