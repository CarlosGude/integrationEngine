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

- **786 tests, 2,169 assertions** in the full suite after adding the audit
  documentation. The preceding `make ci` passed with 783 tests and 2,163 assertions,
  including style and PHPStan max. Documentation data-provider counts change
  when documents or links are added.
- **Line coverage: 98.03%** — 2,188 of 2,232 executable lines, measured with Xdebug.
- **Default covered-only run: 99.54% covered MSI** — 1,098 mutants: 1,092 killed
  by tests, 1 errored, 5 escaped. Three per-mutator ignores were removed;
  thresholds and source exclusions are unchanged.
- **With uncovered code: MSI 98.20%, covered MSI 99.54%** — 1,113 mutants,
  including the same 1,092 killed, 1 errored and 5 escaped, plus 15 uncovered.

The initial plan audit ran `make qa` and the landing's `node --test` (10 tests).
The subsequent quality work ran `make ci` and a separate `--with-uncovered`
measurement successfully. Line coverage above remains the earlier measurement;
it was not regenerated for these test additions. See the [audit](../quality-audit.md)
for the separate Bundle-exclusion experiment and negative checks.

GraphQL batches, form-encoded requests, observability setup, logging and
resilience utilities now have behavioral tests. Mutation coverage alone still
cannot establish whether all code is exercised: check PHPUnit's per-class
coverage report as well. `Bundle` remains excluded from mutation testing below.

Webhook parsing/dispatch, the REST adapter, authorization-header resolution and
the middleware resolver also have full line coverage after the maintenance follow-up.
Remote validation was checked on 2026-09-22 for commit
`6f3107cd98daef8114d05dab9e56dd4979347c9d`:
[main CI passed](https://github.com/CarlosGude/integrationEngine/actions/runs/35735588854)
and [demo contract passed](https://github.com/CarlosGude/integrationEngine/actions/runs/35735588893).
The latter includes the PCOV configuration and validates consuming-app tests and
static analysis. These results apply to that commit; a later release candidate
must pass its own checks.

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

The Bundle/Resources-only experiment generated 1,728 mutants, with 92 escaping;
covered MSI was **94.68%**, below the unchanged 95% gate. Bundle therefore remains
excluded under the fallback explicitly allowed by B1.1. This decision is based
on the measured failure, not a presumption that wiring or generator mutants are
harmless. Improve behavioral tests before narrowing the exclusion.

See the [quality audit](../quality-audit.md) for the experiment, the uncovered-code
diagnostic, removed ignores and architecture prerequisite. `make deptrac` is
available separately but currently reports two Core-to-Symfony violations; it is
not yet an enforcing CI gate.

## Existing mutation exclusions

The table records retained per-mutator exclusions and their assumptions. They
are not all unconditional equivalences: restricted inputs and wall-clock precision
remain review limitations. UnwrapArrayMap and UnwrapArrayFilter are also disabled
globally in the current configuration; their justification still needs review.
No new exclusions were added in the quality audit.

| Mutator | Location | Why it's equivalent |
|---|---|---|
| `TrueValue` | `BatchTokenRetry::prepareWithToken`, `::plan` | The stored value is a hash-set marker read only through `isset()`; its boolean value is never inspected. |
| `TrueValue` | `YamlConfigAdapter::resolvePathPlaceholders` | Same hash-set pattern for `$consumed`, read only via `array_diff_key()` (keys) and `[] === $consumed` (emptiness). |
| `CastFloat` | `DynamicAuthorizationConfig::fromArray` | The preceding `is_int`/`is_float`/`ctype_digit` guard already restricts `ttl` to values where casting to `float` before `< 0` changes nothing. |
| `ReturnRemoval` | `BatchDispatcher::dispatch`, line 36 | `dispatch([])`'s early `return []` and its fallthrough path (three loops over an empty array) produce the identical `[]` result. Pinned to the line so the method's real `return` stays mutated. |
| `CastString` | `ConnectionResolver::resolve` | The missing cast is only observable for a fractional-float `$connection` — not the documented tenant-id shape — via PHP's own float-to-int array-key truncation. |
| `CastString` | `CsvParser::parse` | `mb_convert_encoding()` is declared `string\|false` but only returns `false` for an invalid encoding name, which throws a `ValueError` first on PHP 8. The cast is there for PHPStan, not for runtime. |
| `Throw_` | `ResponseBuilder::applyMapper`, line 41 | `AbstractMapper::map()` is `final` and repeats this same mapper/action check, throwing the same exception with the same arguments, so removing this `throw` changes nothing observable. Pinned to the line: the `NotMappedActionException` above it stays mutated. |
| `LogicalNot` | `LifecycleEventDispatcher::subscribe` | `$this->subscribers[$eventClass][] = …` creates the array by itself, so negating the `isset()` guard it sits behind cannot change the resulting state. |
| `LessThanOrEqualTo` | `HmacSha256SignatureVerifier::verify` | A signature exactly as long as its prefix leaves an empty hash, which `hash_equals()` rejects anyway: `<` and `<=` both end in `return false`. |
| `CastInt` | `TimestampedHmacSignatureVerifier::isWithinTolerance` | `format('U')` returns a numeric string; subtracting it yields the same int with or without the cast. |
| `IncrementInteger`, `DecrementInteger` | `TracingMiddleware::process`, `::processMany` | The `* 1000` seconds-to-milliseconds conversion would need an injectable clock to assert sub-1% precision deterministically; `TracingMiddlewareTest` instead asserts the correct order of magnitude (catches the operator itself being swapped, e.g. `-`↔`+`, `*`↔`/`). Worth revisiting once the engine has an injectable clock elsewhere. |
| `IncrementInteger`, `DecrementInteger` | `IntegrationEngine::send`, lines 111, 123, 125, 137, 148 | The same `* 1000` conversion, here for the durations carried by the lifecycle events. Pinned to those lines so the rest of the method stays mutated. |

If a future mutant appears "equivalent" but isn't in this table, don't add a
new `ignore` entry to make CI pass — write the test instead, or bring it here
with the same reasoning this table requires: which observable behavior, given
how the code is actually called, makes the mutation unkillable.
