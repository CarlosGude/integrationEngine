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
make ci     # qa + deptrac + mutation — run before opening a PR
```

## Code quality

Quality gates (style, static analysis, tests, mutation), their commands, and
current MSI: see [`docs/advanced/QUALITY.md`](docs/advanced/QUALITY.md).

`make deptrac` enforces architectural layers locally and in CI, with no baseline
and no uncovered dependencies. Core may depend only on PSR and its own classes;
legacy resilience facades live in an explicit outer compatibility layer. See the
[quality audit](docs/quality-audit.md) and [ADR 0015](docs/adr/0015-resilience-classification-boundary.md).

The documentation tests run in the normal PHPUnit suite. Use Markdown links
relative to the document containing them. Backtick path mentions are relative
to the repository root; use a Markdown link when you mean a navigable relative
path. CLAUDE.md is the canonical agent guide, so update it rather than creating
another guide under a separate agent directory.

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

2. **UPGRADE-X.md** — created for each MAJOR version with migration guide for breaking changes.

3. **Commit messages** — follow conventional commits (optional but encouraged):
   - `feat: description` (MINOR if new)
   - `fix: description` (PATCH if fix)
   - `feat!: description` or `BREAKING CHANGE:` footer (MAJOR)

4. **Git tags** — `vX.Y.Z` format.

5. **GitHub releases** — populated from CHANGELOG section for that version.

6. **Packagist** — automatically synced from GitHub releases.

## When to Open a PR

- Any change to `src/` or `tests/`.
- Changes to `docs/advanced/QUALITY.md`, `ARCHITECTURE.md`, or version-related files.
- Anything affecting public API or behavior.

Skip PRs only for trivial documentation (typos in README examples, comment fixes) when committing directly to `main` is acceptable to your team.

## Further reading

- [`docs/ARCHITECTURE.md`](./docs/ARCHITECTURE.md) — design decisions: why actions are stateless,
  how path resolution works, the mapper invariant, cache behaviour, and the
  DTO/domain boundary.
- [`CLAUDE.md`](./CLAUDE.md) — the engine's contracts and conventions in agent-readable
  form; also the context for AI-assisted integration generation.
