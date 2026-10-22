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
make test      # phpunit
make stan      # phpstan level max
make cs        # php-cs-fixer (dry-run)
make cs-fix    # php-cs-fixer, applying fixes
make deptrac   # architectural layers
make mutation  # Infection, including uncovered code
make qa        # cs + stan + test — run before each commit
make ci        # qa + deptrac + mutation — run before opening a PR (alias: make pre-commit)
make docs-editions  # regenerate the single-file documentation editions
```

## Code quality

Quality gates (style, static analysis, tests, mutation), their commands, and
current MSI: see [`docs/advanced/quality.md`](docs/advanced/quality.md).

`make deptrac` enforces the [architectural layers](docs/architecture.md#repository-layers)
locally and in CI, with no baseline and no uncovered dependencies. See
[ADR 0015](docs/adr/0015-resilience-classification-boundary.md) for why legacy
resilience facades live in an explicit outer compatibility layer.

The documentation tests run in the normal PHPUnit suite. Use Markdown links
relative to the document containing them. Backtick path mentions are relative
to the repository root; use a Markdown link when you mean a navigable relative
path. CLAUDE.md is the canonical agent guide, so update it rather than creating
another guide under a separate agent directory.

Each concept has one canonical page; link to it instead of restating it. After
changing any page, run `make docs-editions` (Python 3) to regenerate the
single-file editions in `docs/documentation-{minimal,extended,full}.md` and
commit them with the change.

A red *Demo contract* job means a potential breaking change: investigate the
failure, then justify any contract change with an ADR or redesign it. Dependency
resolution or runner failures must also be diagnosed before attributing a red
job to a public API change.

## Versioning Policy

This project adheres to [Semantic Versioning](https://semver.org/):

### MAJOR

Increment on **breaking changes** to public APIs:

- Renamed classes, interfaces, or methods used by applications integrating this bundle.
- Removed public methods or class properties without deprecation period.
- Changed method signatures that break existing callsites.
- Changed behavior that breaks integrations (e.g., cache key strategy, authentication flow).

**Examples from history:**
- **v2.0** → **v3.0**: `sendMany()` batch isolation; `DoubleDecorator` → pipeline.
- **v3.0** → **v4.0**: `ClientMiddlewareInterface` → `AbstractClientMiddleware`; middleware discovery via tagging.

### MINOR

Increment on **backward-compatible additions**:

- New public methods or optional parameters.
- New configuration options.
- New middleware or adapter integrations (existing middleware unchanged).
- Documentation improvements.

**Examples:**
- v2.3: Added `DynamicBaseUrlClientInterface`.
- v4.1: Added Symfony 6.4+ support; PHPStan Symfony extension.

### PATCH

Increment on **backward-compatible bug fixes**:

- Fixes to existing behavior without breaking API.
- Security patches.
- Test and documentation corrections.
- Performance improvements.

## Release Process

1. **CHANGELOG.md** — updated with each release, following [Keep a Changelog](https://keepachangelog.com/) format.
   - `## [Unreleased]` section with in-progress changes.
   - Promoted to version number upon release.
   - Sections: `Added`, `Changed`, `Deprecated`, `Removed`, `Fixed`, `Security`.

2. **docs/upgrade-N.0.md** — created for each MAJOR version with migration guide for breaking changes, and listed in [`docs/documentation.md`](docs/documentation.md).

3. **Commit messages** — follow conventional commits (optional but encouraged):
   - `feat: description` (MINOR if new)
   - `fix: description` (PATCH if fix)
   - `feat!: description` or `BREAKING CHANGE:` footer (MAJOR)

4. **Git tags** — `vX.Y.Z` format.

5. **GitHub releases** — populated from CHANGELOG section for that version.

6. **Packagist** — automatically synced from GitHub releases.

## When to Open a PR

- Any change to `src/` or `tests/`.
- Changes to `docs/advanced/quality.md`, `docs/architecture.md`, or version-related files.
- Anything affecting public API or behavior.

Skip PRs only for trivial documentation (typos in README examples, comment fixes) when committing directly to `main` is acceptable to your team.

## Further reading

- [`docs/documentation.md`](./docs/documentation.md) — the documentation index.
- [`docs/architecture.md`](./docs/architecture.md) — scope, layers, request pipeline
  and the DTO/domain boundary.
- [`CLAUDE.md`](./CLAUDE.md) — the engine's contracts and conventions in agent-readable
  form; also the context for AI-assisted integration generation.
