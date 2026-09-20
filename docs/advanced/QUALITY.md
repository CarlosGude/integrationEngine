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

As of 2026-09-20, measured locally on PHP 8.5:

- **Covered Code MSI: 98–99%** — above the 95% floor.
- 901 mutants generated; 9 to 11 escape depending on the run.

The survivors are the UUID bit masks described below, which is also why the
number moves between runs. Every other mutant is killed by a test, and nothing
here lowers a threshold to cover anything up.

Infection also reports **Mutation Code Coverage: 100%** and no uncovered
mutants. Don't read that as full coverage: it generates no mutants at all for
lines no test executes, so code like `GraphQLClientAdapter::sendMany()` — which
has no test — counts neither way. `vendor/bin/phpunit --coverage-clover` is what
answers that question; it reports 86% of lines.

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
| `Throw_` | `ResponseBuilder::applyMapper`, line 41 | `AbstractMapper::map()` is `final` and repeats this same mapper/action check, throwing the same exception with the same arguments, so removing this `throw` changes nothing observable. Pinned to the line: the `NotMappedActionException` above it stays mutated. |
| `LogicalNot` | `LifecycleEventDispatcher::subscribe` | `$this->subscribers[$eventClass][] = …` creates the array by itself, so negating the `isset()` guard it sits behind cannot change the resulting state. |
| `LessThanOrEqualTo` | `HmacSha256SignatureVerifier::verify` | A signature exactly as long as its prefix leaves an empty hash, which `hash_equals()` rejects anyway: `<` and `<=` both end in `return false`. |
| `LogicalOr` | `TimestampedHmacSignatureVerifier::verify` | With no provided hashes the loop below returns `false` anyway, and a non-numeric timestamp casts to `0`, outside any sane tolerance — so `\|\|` and `&&` agree on every input this verifier can be called with. Both operators sit on one line, so this entry is scoped to the method and also covers the second `\|\|`, which tests do kill: the only one in this table that gives something up. |
| `CastInt` | `TimestampedHmacSignatureVerifier::verify`, line 49 | Only a non-canonical numeric timestamp (`"1700000000.0"`, `" 1700000000"`) signs a different string with and without the cast, and no provider sends one. Pinned to the line so the cast feeding `isWithinTolerance()` stays mutated. |
| `CastInt` | `TimestampedHmacSignatureVerifier::isWithinTolerance` | `format('U')` returns a numeric string; subtracting it yields the same int with or without the cast. |
| `IncrementInteger`, `DecrementInteger` | `TracingMiddleware::process`, `::processMany` | The `* 1000` seconds-to-milliseconds conversion would need an injectable clock to assert sub-1% precision deterministically; `TracingMiddlewareTest` instead asserts the correct order of magnitude (catches the operator itself being swapped, e.g. `-`↔`+`, `*`↔`/`). Worth revisiting once the engine has an injectable clock elsewhere. |
| `Foreach_`, `Ternary` | `ShopifyWebhookController::handleShopifyWebhook`, lines 68 and 69 | The headers this loop flattens are handed to the mapper, and no Shopify mapper reads them — the flattening is there for mappers that would. Nothing the controller exposes can tell the mutants apart. |
| `IncrementInteger`, `DecrementInteger` | `IntegrationEngine::send`, lines 111, 123, 125, 137, 148 | The same `* 1000` conversion, here for the durations carried by the lifecycle events. Pinned to those lines so the rest of the method stays mutated. |

If a future mutant appears "equivalent" but isn't in this table, don't add a
new `ignore` entry to make CI pass — write the test instead, or bring it here
with the same reasoning this table requires: which observable behavior, given
how the code is actually called, makes the mutation unkillable.

### Not ignored: the failure id's UUID bits

`ProcessWebhookHandler::generateFailureId()` builds a v4 UUID by masking two
bytes (`& 0x0F | 0x40`, `& 0x3F | 0x80`). `WebhookDlqTest` asserts the v4
format, which kills every mutant that moves a byte index, and every mask shift
that lands outside the version/variant nibbles. What survives are the shifts
that only move bits the format leaves free (`| 0x40` → `| 0x41` is still
version 4), plus two that die or survive depending on what `random_bytes()`
returned that run — so this method makes the MSI wobble by a mutant or two
between runs.

They stay unignored on purpose. Both masks share a line with the byte index
next to them, and Infection's `ignore` is per class, method or line: silencing
the equivalent masks would silence the index mutants the test does kill. A
couple of surviving mutants is the cheaper price.
