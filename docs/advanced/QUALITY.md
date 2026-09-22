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

As of 2026-09-22, measured locally on PHP 8.5.6 (unreleased working tree):

- **778 tests, 2,155 assertions**, style and PHPStan max passing.
- **Line coverage: 98.03%** — 2,188 of 2,232 executable lines, measured with Xdebug.
- **Covered Code MSI: 99.54%** — 1,093 mutants: 1,087 killed by tests,
  1 errored, 5 escaped. Existing thresholds and exclusions are unchanged.

GraphQL batches, form-encoded requests, observability setup, logging and
resilience utilities now have behavioral tests. Mutation coverage alone still
cannot establish whether all code is exercised: check PHPUnit's per-class
coverage report as well. `Bundle` remains excluded from mutation testing below.

Webhook parsing/dispatch, the REST adapter, authorization-header resolution and
the middleware resolver also have full line coverage after the maintenance follow-up.
The demo contract workflow now enables PCOV; its modified remote run remains to
be verified. These local results do not stand in for that consuming-app check.

### Surviving mutants retained without new exclusions

- Four mutations change `LoggingMiddleware`'s milliseconds conversion factor
  from 1,000 to 999 or 1,001, in the success and failure paths. Tests verify
  integer millisecond durations and their order of magnitude with a delayed
  response, but cannot reliably distinguish a 0.1% wall-clock variation.
- Changing the bit-width guard in `ExponentialBackoffPolicy::getBackoffMs()`
  from `>=` to `>` is equivalent: at that boundary the signed shift becomes
  negative and the subsequent multiplication guard throws the same
  `OverflowException`. Larger retry numbers still hit the first guard.

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
| `ReturnRemoval` | `BatchDispatcher::dispatch`, line 36 | `dispatch([])`'s early `return []` and its fallthrough path (three loops over an empty array) produce the identical `[]` result. Pinned to the line so the method's real `return` stays mutated. |
| `CastString` | `ConnectionResolver::resolve` | The missing cast is only observable for a fractional-float `$connection` — not the documented tenant-id shape — via PHP's own float-to-int array-key truncation. |
| `CastString` | `CsvParser::parse` | `mb_convert_encoding()` is declared `string\|false` but only returns `false` for an invalid encoding name, which throws a `ValueError` first on PHP 8. The cast is there for PHPStan, not for runtime. |
| `LogicalAnd` | `CsvParser::parse` | Turning `&&` into `\|\|` only widens the transcoding guard to the two cases it excludes: encoding `'UTF-8'`, and `null`, which makes `mb_convert_encoding()` fall back to the UTF-8 internal encoding. Both transcode UTF-8 to UTF-8, i.e. identity. |
| `Throw_` | `ResponseBuilder::applyMapper`, line 41 | `AbstractMapper::map()` is `final` and repeats this same mapper/action check, throwing the same exception with the same arguments, so removing this `throw` changes nothing observable. Pinned to the line: the `NotMappedActionException` above it stays mutated. |
| `LogicalNot` | `LifecycleEventDispatcher::subscribe` | `$this->subscribers[$eventClass][] = …` creates the array by itself, so negating the `isset()` guard it sits behind cannot change the resulting state. |
| `LessThanOrEqualTo` | `HmacSha256SignatureVerifier::verify` | A signature exactly as long as its prefix leaves an empty hash, which `hash_equals()` rejects anyway: `<` and `<=` both end in `return false`. |
| `LogicalOr` | `TimestampedHmacSignatureVerifier::verify` | With no provided hashes the loop below returns `false` anyway, and a non-numeric timestamp casts to `0`, outside any sane tolerance — so `\|\|` and `&&` agree on every input this verifier can be called with. Both operators sit on one line, so this entry is scoped to the method and also covers the second `\|\|`, which tests do kill: the only one in this table that gives something up. |
| `CastInt` | `TimestampedHmacSignatureVerifier::verify`, line 49 | Only a non-canonical numeric timestamp (`"1700000000.0"`, `" 1700000000"`) signs a different string with and without the cast, and no provider sends one. Pinned to the line so the cast feeding `isWithinTolerance()` stays mutated. |
| `CastInt` | `TimestampedHmacSignatureVerifier::isWithinTolerance` | `format('U')` returns a numeric string; subtracting it yields the same int with or without the cast. |
| `IncrementInteger`, `DecrementInteger` | `TracingMiddleware::process`, `::processMany` | The `* 1000` seconds-to-milliseconds conversion would need an injectable clock to assert sub-1% precision deterministically; `TracingMiddlewareTest` instead asserts the correct order of magnitude (catches the operator itself being swapped, e.g. `-`↔`+`, `*`↔`/`). Worth revisiting once the engine has an injectable clock elsewhere. |
| `IncrementInteger`, `DecrementInteger` | `IntegrationEngine::send`, lines 111, 123, 125, 137, 148 | The same `* 1000` conversion, here for the durations carried by the lifecycle events. Pinned to those lines so the rest of the method stays mutated. |

If a future mutant appears "equivalent" but isn't in this table, don't add a
new `ignore` entry to make CI pass — write the test instead, or bring it here
with the same reasoning this table requires: which observable behavior, given
how the code is actually called, makes the mutation unkillable.
