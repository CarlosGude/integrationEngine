# IntegrationEngine — Testing Guide

The test suite is organized around contracts rather than around one giant kernel test. Most behavior is exercised with direct objects/fakes; bundle wiring gets focused container/compiler-pass tests.

## Commands

```bash
make test       # PHPUnit
make stan       # PHPStan
make deptrac    # dependency rules
make cs         # php-cs-fixer dry run
make mutation   # Infection with uncovered code included
make qa         # cs + stan + test
make ci         # qa + deptrac + mutation
```

Thresholds and measured CI evidence belong in [advanced/QUALITY.md](./advanced/QUALITY.md).

## Test areas

### Core engine

Tests under `tests/Core/` cover:

- single request orchestration and response mapping;
- mapper/action reciprocity and response-less actions;
- context-only path resolution and preservation of matching body fields;
- dynamic auth cache behavior and the single cached-token 401 refresh;
- runtime connection resolution and cache discrimination;
- batch success/failure isolation, strict unwrapping and batch mapper checks;
- lifecycle event metadata/durations;
- resilience classification and backoff contracts;
- webhook mapper/signature/domain contracts.

### Infrastructure

Tests under `tests/Infrastructure/` exercise the real adapter behavior around fake/mock transports:

- REST request bodies, headers, response headers and dynamic base URLs;
- form encoding and per-action timeout propagation;
- GraphQL request bodies, errors, headers, dynamic base URLs and concurrent batch behavior;
- request middleware semantics, including the sequential batch fallback;
- caching/tracing/logging middleware;
- retry behavior and concurrent retries;
- host allowlists/private-network blocking without real external network calls;
- YAML configuration, webhook parsing and lifecycle helper behavior.

### Bundle

Tests under `tests/Bundle/` focus on configuration and generated/wired services:

- configuration validation;
- compiler-pass client/transport/middleware/connection wiring;
- optional webhook component wiring;
- generator output for integrations/webhooks/observability;
- debug command behavior.

These tests are intentionally narrower than booting a full application kernel for every case.

### PHPStan extension

`tests/PHPStan/` contains rule tests and intentionally invalid fixtures. The fixtures use a dedicated extension so the repository's normal PHPStan run does not treat them as source code that should pass.

## Batch assertions

`sendMany()` returns `BatchResultCollection`. Tests should assert success/failure per original key rather than relying on exception order:

```php
$results = $engine->sendMany($requests);

self::assertFalse($results['first']->isSuccess());
self::assertTrue($results['second']->isSuccess());
self::assertArrayHasKey('second', $results->responses());
self::assertArrayHasKey('first', $results->errors());
```

Use `sendManyOrFail()` only when the behavior under test is the strict unwrapping contract. The engine still dispatches the complete batch before the first failed result is thrown during unwrapping.

## Webhook tests

Current webhook tests should pin the v8 order of operations:

1. raw signature validation;
2. JSON object validation;
3. type/id dot-path resolution;
4. mapper lookup/type consistency;
5. typed `MappedRemoteEvent` creation;
6. structured rejection without secrets.

Do not add tests for removed bundle idempotency classes; v8 deliberately moved deduplication to the consuming application.

## Transport tests

Use Symfony `MockHttpClient`/`MockResponse` or small spy transports. No test should depend on a live external API. For private-network behavior, the fake must expose the transport metadata/progress callbacks that Symfony's decorator actually reads; a plain mock response is not sufficient evidence by itself.

## Mutation testing

Mutation results are a signal about exercised behavior, not a substitute for line/branch coverage or architectural tests. Keep surviving/equivalent/uncovered mutants visible rather than excluding broad source areas just to improve a percentage.

The repository's current Infection policy and measured run are documented in [advanced/QUALITY.md](./advanced/QUALITY.md).
