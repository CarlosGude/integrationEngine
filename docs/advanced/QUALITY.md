# Quality gates

Thresholds are defined only in infection.json5. Local and remote commands use
that configuration; no CLI threshold override, baseline or suppressed mutator
is used.

| Gate | Command | Requirement |
|---|---|---|
| Style | `make cs` | No changes |
| Static analysis | `make stan` | PHPStan max over src and tests, no baseline |
| Tests | `make test` | Green, no skipped/incomplete tests |
| Architecture | `make deptrac` | Zero violations, uncovered dependencies or skipped violations |
| Mutation | `make mutation` | MSI >=85%, covered MSI >=90%; includes uncovered code |
| Landing | `cd landing && node --test` | EN/ES parity and content checks; dedicated CI job |

```bash
make qa   # style + static analysis + tests
make ci   # qa + deptrac + mutation
```

`make pre-commit` is an alias of `make ci`. Mutation runs
`vendor/bin/infection --threads=max --show-mutations --with-uncovered` locally
and in CI. QualityConfigTest guards the thresholds, existing excluded paths,
the absence of suppressed default mutators and inclusion of uncovered code.

## Measurements and scope

Measured 2026-09-22 on PHP 8.5.6 with Xdebug:

- `make ci`: **796 tests, 2,238 assertions**, style, PHPStan and Deptrac passing.
- Deptrac: **0 violations, 0 skipped violations, 0 uncovered dependencies**.
- Infection: **1,151 mutants**, 1,104 killed, 1 errored, 31 escaped, 15 uncovered.
- **MSI 96.00%; covered MSI 97.27%**. All default mutators enabled, no ignores.
- Landing: **23 tests** passing in its independent Node job.

Counts reflect that execution; documentation data-provider counts can change
when documents are updated. The [quality audit](../quality-audit.md) includes
commit-specific remote results. PHPUnit line coverage and mutation coverage are different measurements;
passing MSI does not establish complete line coverage.

All default Infection mutators are enabled. Previous ignore entries, including
wall-clock conversions and presumed equivalent mutants, were removed, as were
the global UnwrapArrayMap/UnwrapArrayFilter exclusions. A fractional-connection
regression test now checks that two scalar IDs cannot share cached credentials.
Tests also cover the new Core backoff defaults independently of the legacy facade.

Survivors remain visible in var/infection/infection.log; they count against the
unchanged thresholds. They include hash-set values observed only through isset,
redundant guards, wall-clock conversion precision, trait visibility and error
paths. They are not all claimed equivalent. Uncovered mutations remain visible
and reduce overall MSI instead of being silently omitted.

## Bundle exclusion

The current all-default-mutators experiment with only Bundle/Resources excluded
produced **1,809 mutants: 1,651 killed, 2 errors, 1 syntax error, 121 escaped and
34 uncovered**. Covered MSI was **93.18%**; that historical run predated the current 90% covered-MSI gate.

Bundle therefore remains excluded under the fallback allowed by B1.1. No other
source exclusion was widened and no threshold was reduced. Improve behavioral
command/generator/container tests before narrowing this boundary. Compatibility
facades and the new classification code remain in the mutated source set.

The earlier 94.68% Bundle experiment used the old, narrower mutator set; it is
historical evidence, not the current measurement.

## Architectural boundary

Deptrac checks src and tests with explicit Core, Infrastructure, Bundle,
Compatibility, Utils, PSR, Symfony and PHPUnit layers. Core allows PSR only.
The two old resilience APIs keep their public names in an explicit outer
Compatibility layer; Core never references those facades. See
[ADR 0015](../adr/0015-resilience-classification-boundary.md) and
[the architecture rules](../ARCHITECTURE.md).

The initial two Core-to-Symfony violations were fixed in the separate
[architecture PR #5](https://github.com/CarlosGude/integrationEngine/pull/5)
before activating the gate. Negative verification confirms both forbidden
Symfony dependencies and unclassified external dependencies fail the command.

## Compatibility and negative verification

The test matrix covers PHP 8.2/8.3/8.4, Symfony 6.4/7.4/8.x and lowest/stable
resolutions, excluding PHP 8.2/8.3 with Symfony 8. Symfony documents PHP >=8.2 for
[7.4](https://symfony.com/releases/7.4) and PHP >=8.4 for
[8.0](https://symfony.com/releases/8.0), matching these exclusions.

The generator smoke job runs in a real PHP 8.2 Symfony app. The demo contract
installs this bundle checkout through a path repository and runs the consuming
application's tests and PHPStan. The temporary
[negative-verification PR #6](https://github.com/CarlosGude/integrationEngine/pull/6)
proved both checks fail for the intended regressions and was closed without merge.
The [audit](../quality-audit.md) links the exact failing jobs and diagnostic messages.
