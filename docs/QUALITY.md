# Quality gates

One standard, defined only in the files below — nowhere else in the repo
declares its own thresholds.

| Gate | Command | Threshold |
|---|---|---|
| Style | `make cs` (`vendor/bin/php-cs-fixer fix --dry-run --diff`) | 0 changes |
| Static analysis | `make stan` (`vendor/bin/phpstan analyse --memory-limit=1G`) | `level: max` over `src` **and** `tests`, 0 errors, no baseline |
| Tests | `make test` (`vendor/bin/phpunit`) | 100% green, no `skipped`/`incomplete` |
| Mutation | `make mutation` (`vendor/bin/infection --threads=max --show-mutations`) | `minMsi: 85`, `minCoveredMsi: 95` — both defined only in `infection.json5` |

```bash
make qa   # cs + stan + test — before each commit
make ci   # qa + mutation — before opening a PR
```

No command above accepts its own `--min-msi`/`--min-covered-msi` flag; a test
(`tests/Quality/QualityConfigTest.php`) enforces that `infection.json5` stays
the single source of these numbers, and that its `source.excludes` and
`phpunit.xml.dist`'s `<exclude>` list only point at files that actually exist.

## Current numbers

As of 2026-09-16, measured locally on PHP 8.5:

- **Mutation Code Coverage: 100%** — every mutant is exercised by at least one test run.
- **Covered Code MSI: 100%** — every exercised mutant is killed.
- 578 mutants generated, 578 killed, 0 escaped.

Both are above the 85/95 floor; nothing here lowers a threshold to pass.

## `Bundle` exclusion from mutation testing

`infection.json5`'s `source.excludes` still excludes all of `Bundle` (DI
extension, compiler pass, generator, profiler templates) rather than only
`Bundle/Resources`. Symfony DI wiring and code generation are exercised by
`tests/Bundle/*` through container assertions and generated-file checks, not
through mutation-sensitive business logic — running mutation testing over
service-definition builders produces mostly noise (e.g. mutating a
`Reference('foo')` string literal) rather than signal. Revisiting this
(narrowing the exclusion to `Bundle/Resources` once there's a concrete need)
is future work, not something silently dropped.

## Equivalent mutants

A handful of mutants are intentionally excluded via per-mutator `ignore`
entries in `infection.json5` — not because the code is untested, but because
no observable behaviour distinguishes the mutant from the original given how
the surrounding code is actually used. Each is scoped to the exact class or
method, never to a whole mutator globally.

| Mutator | Location | Why it's equivalent |
|---|---|---|
| `TrueValue` | `BatchTokenRetry::prepareWithToken`, `::plan` | The stored value is a hash-set marker read only through `isset()`; its boolean value is never inspected. |
| `TrueValue` | `YamlConfigAdapter::resolvePathPlaceholders` | Same hash-set pattern for `$consumed`, read only via `array_diff_key()` (keys) and `[] === $consumed` (emptiness). |
| `CastFloat` | `DynamicAuthorizationConfig::fromArray` | The preceding `is_int`/`is_float`/`ctype_digit` guard already restricts `ttl` to values where casting to `float` before `< 0` changes nothing. |
| `ReturnRemoval` | `IntegrationEngine::dispatchBatch` | `dispatchBatch([])`'s early `return []` and its fallthrough path (three loops over an empty array) produce the identical `[]` result. |
| `CastString` | `IntegrationEngine::resolveConnection` | The missing cast is only observable for a fractional-float `$connection` — not the documented tenant-id shape — via PHP's own float-to-int array-key truncation. |
| `IncrementInteger`, `DecrementInteger` | `TracingMiddleware::process`, `::processMany` | The `* 1000` seconds-to-milliseconds conversion would need an injectable clock to assert sub-1% precision deterministically; `TracingMiddlewareTest` instead asserts the correct order of magnitude (catches the operator itself being swapped, e.g. `-`↔`+`, `*`↔`/`). Worth revisiting once the engine has an injectable clock elsewhere. |

If a future mutant appears "equivalent" but isn't in this table, don't add a
new `ignore` entry to make CI pass — write the test instead, or bring it here
with the same reasoning this table requires: which observable behavior, given
how the code is actually called, makes the mutation unkillable.
