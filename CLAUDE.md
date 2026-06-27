# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

IntegrationEngine is a Symfony bundle that enforces a predictable, standardized structure for external API integrations. Every integration endpoint is split into exactly two responsibilities: **Request** (what goes in) and **Response** (what comes out), with a **Mapper** bridging the raw HTTP response to a typed DTO.

## Commands

```bash
make install        # composer install
make test           # run all tests via phpunit
make cs             # check code style (dry-run)
make cs-fix         # fix code style in-place
make stan           # phpstan level max analysis
make qa             # cs + test
make ci             # cs + stan + test + mutation
make pre-commit     # cs-fix + stan + test + mutation
```

**Run a single test file:**
```bash
./vendor/bin/phpunit tests/Core/AbstractActionTest.php
```

**Run a single test method:**
```bash
./vendor/bin/phpunit --filter=testGetPath tests/Core/AbstractActionTest.php
```

**Run phpstan on specific paths:**
```bash
make stan PATHS='src/Core/IntegrationEngine.php'
```

## Architecture

### Core Abstractions

| Class | Role |
|---|---|
| `AbstractAction` | Immutable value object for one API endpoint — declares HTTP method, path, auth config, and optional mapper |
| `AbstractMapper` | Transforms raw response `array` → typed `ResponseInterface`. One mapper per action, enforced at runtime |
| `ResponseInterface` | DTO marker; must implement `toArray()`. Represents the external API shape, not domain objects |
| `ActionContextInterface` | Carries dynamic values at call time (path params, filter values). Default: `DefaultActionContext` |
| `ActionBodyInterface` | Request payload converted to JSON (REST) or GraphQL query |
| `IntegrationEngine` | Orchestrator: Config → Client → Mapper → Response. Also handles dynamic auth token caching |
| `IntegrationRegistry` | Service locator; returns the `IntegrationEngine` instance for a named integration |
| `EngineRequest` | One request inside a batch: the same four `send()` arguments as an immutable value object |
| `BatchResult` | Outcome of one batch item: `isSuccess()`, `response()` (rethrows on failure), `error()` |
| `BatchResultCollection` | Keyed collection of `BatchResult`s returned by `sendMany()`. Iterable, countable, `ArrayAccess`. Exposes `responses()`, `errors()`, `hasFailures()`, `mapWith()` |
| `AbstractBatchMapper` | Second-stage mapper for homogeneous batches (same action, N contexts). `consolidate()` receives already-mapped DTOs; called via `BatchResultCollection::mapWith()` |
| `BatchTokenRetry` | Internal: tracks which batch items hold a pre-cached token (retryable on 401) vs a token first fetched in this batch (already fresh). Used by `sendMany()` to coordinate the shared-token retry |

### Key Ports and Adapters

- **`ConfigPort`** — loads action config from YAML (`YamlConfigAdapter`)
- **`ClientInterface`** — executes HTTP. Built-in: `SymfonyHttpClientAdapter` (REST) and `GraphQLClientAdapter`. Tagged `integration_engine.client_adapter`; multiple adapters are discovered automatically
- **`BatchClientInterface`** — optional capability: executes a batch of `PreparedRequest`s concurrently, returning raw payload or Throwable per key. `SymfonyHttpClientAdapter` implements it (lazy responses: dispatch all, then consume). Clients without it fall back to sequential sends
- **`ClientMiddlewareInterface`** — cross-cutting concern hook with `process()` (single) and `processMany()` (batch). `AbstractClientMiddleware` provides a default `processMany()` passthrough — override only when batch-aware behaviour is needed. Tag services with `integration_engine.middleware` and an optional `priority` attribute to inject into the chain
- **`MiddlewareClient`** — always wraps the HTTP adapter. Layer order (outermost → innermost): `CachingMiddleware` → user middlewares by descending priority → `TracingMiddleware` (debug only) → HTTP adapter. Always implements `BatchClientInterface` and `DynamicBaseUrlClientInterface`
- **`CachePort`** — caches dynamic auth tokens with `get`/`set`/`delete` (`Psr6CacheAdapter` wrapping Symfony's PSR-6 cache)

### Data Flow

```
Application service
  → IntegrationRegistry::get(NAME) → IntegrationEngine
  → engine->send(actionName, context, body, headers)
  → ConfigPort::getAction()
  → [if dynamic auth] fetch + cache token, rebuild action with static auth
  → MiddlewareClient::send(action, context, headers)
      → CachingMiddleware   (cache hit → return early; miss → continue)
      → [user middlewares, outermost first by descending priority]
      → TracingMiddleware   (dev/test only — records timing, action, status)
      → HTTP adapter        → raw array
  → AbstractMapper::map(action, rawArray) → ResponseInterface
  → return DTO to application service
```

Application services translate infrastructure DTOs to domain objects — DTOs must not leak into the domain.

### Batch / Parallel Requests

`sendMany(array<key, EngineRequest>): BatchResultCollection` executes a batch (mixed actions allowed), preserving the caller's keys. Individual failures never abort the batch — each key resolves to a success or failure `BatchResult`. `sendManyOrFail()` unwraps to `array<key, ResponseInterface>` instead, throwing the first failure in request order (the whole batch still executes). Requests run concurrently when the client implements `BatchClientInterface`; otherwise sequentially.

Dynamic auth in batches: the token is resolved once per token action (not per item). Items that entered the batch with a pre-batch cached token get the single 401 retry with one shared fresh token; a token fetched during the batch counts as fresh for every item, so their 401s are final.

### Path Resolution

Two approaches, in order of complexity:

1. **YAML placeholder** — define `{param}` in path; pass required params via `DefaultActionContext`
2. **Custom context** — implement `PathResolvableContextInterface::resolvePath()` for optional params or complex logic; return `null` to fall back to the placeholder resolver

### Dynamic Authentication

When an action has a dynamic auth config, the engine:
1. Calls the designated token action
2. Caches the result per integration per token action (default cache: `cache.app`)
3. Reconstructs the original action with the token as static auth
4. If a **cached** token is rejected with HTTP 401, deletes it and retries once with a fresh token (fresh-token 401s and non-401 errors propagate without retry)

Per-worker token fetches under PHP-FPM are expected and by design.

### Mapper Invariant

Every `AbstractMapper` must declare `getAction(): string` returning its paired action class. The engine validates this at map-time — mismatches throw an exception. Share mapping logic via static methods or traits, not inheritance chains.

## Testing

Tests live in `tests/` with `Fake/` subdirectories containing minimal test doubles (no mocks on internal methods).

- `tests/Core/` — engine contract, action path resolution, dynamic auth (including 401 retry), mapper invariant
- `tests/Infrastructure/` — HTTP adapter headers, GraphQL adapter, PSR-6 cache, adapter resolver
- `tests/Bundle/` — bundle configuration, DI extension, compiler pass, generator, `make:integration` command
- `tests/Fake/` — `FakeClient`, `FakeCache`, `FakeConfigPort`, `FakeContext`, `FakeMiddleware`, etc.

## Creating a New Integration

Scaffold with the generator command (available inside a Symfony app using this bundle):
```bash
php bin/console make:integration MyApi GetEmployee
```

Then implement in order: `GetEmployeeAction` → `GetEmployeeRequest` (optional) → `GetEmployeeResponse` → `GetEmployeeMapper` → add entry to `MyApi.yaml`.

## Bundle Configuration (in consuming apps)

```yaml
integration_engine:
    integrations:
        my_api:
            base_url: 'https://api.example.com'
            config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/MyApi/MyApi.yaml'
            headers: {}       # optional global headers
            cache_service: ~  # optional, default: cache.app
            client_service: ~ # optional fully custom client
```
