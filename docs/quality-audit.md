# Quality audit — 2026-09-22

This records the quality work following the [bundle plan](PLAN-STATUS.md).
The implementation baseline is `6f3107cd98daef8114d05dab9e56dd4979347c9d`;
measurements below also include the local quality changes. They are not remote
CI results for a new commit.

## Architecture prerequisite

Installed [deptrac/deptrac](https://packagist.org/packages/deptrac/deptrac) 4.7.2
as a development dependency, with configuration in the repository root and
`make deptrac` as a standalone command. The package supports PHP 8.2.
The rules keep Core limited to PSR, explicitly classify Utils and PHPUnit,
and fail on uncovered dependencies. No baseline or skipped violations is used.

The first run reports **2 violations, 0 uncovered dependencies, 0 warnings**:

| Source | Forbidden dependency | Line |
|---|---|---|
| Core/Resilience/ErrorClassifier | Symfony Contracts HttpExceptionInterface | 75 |
| Core/Resilience/ErrorClassifier | Symfony Contracts TransportExceptionInterface | 87 |

Both dependencies are exercised behavior: the current tests expect Symfony HTTP
exceptions to preserve their status and transport errors to remain transient.
Deleting those branches to make Deptrac pass would change the public behavior.

**B1.6 is blocked on a separate architecture fix**, as required by the original
task. Production code is unchanged in this quality patch. The prerequisite PR
must define an infrastructure adapter for Symfony exceptions and a Core-owned
classification contract, account for direct static ErrorClassifier callers and
ExponentialBackoffPolicy, and explain any migration. Do not hide the dependency
with string class names, reflection, a baseline or a broader Core rule.

After that prerequisite passes compatibility tests and `make deptrac`, add the
command to `make ci` and an enforcing PHP 8.4 job in the main workflow. This
patch deliberately does not claim an active architecture CI gate or zero violations.

An isolated negative fixture placing a Symfony HttpClientInterface dependency in
Core exits **1**, with one forbidden-layer violation and no uncovered dependencies.
This establishes that the rule detects a newly introduced dependency independently
of the existing ErrorClassifier problem.

## Mutation measurements and decisions

All runs use Infection 0.33.2, PHP 8.5.6 with Xdebug, and thresholds **85/95**.
The Bundle experiment used a temporary configuration, leaving the committed
exclusions unchanged.

| Experiment | Result | Decision |
|---|---|---|
| Narrow Bundle exclusion to Bundle/Resources | 1,728 mutants: 1,633 killed, 2 errors, 1 syntax error, 92 escaped; covered MSI **94.68%**, exit 1 | Keep Bundle excluded for now, as B1.1 allows. |
| Default command after removing three ignores | 1,098 mutants: 1,092 killed, 1 error, 5 escaped; covered MSI **99.54%**, exit 0 | Existing quality gate passes. |
| Include uncovered code with the existing Bundle exclusion | 1,113 mutants: 1,092 killed, 1 error, 5 escaped, 15 uncovered; MSI **98.20%**, covered MSI **99.54%**, exit 0 | Keep the coverage limitation explicit and retain a separate diagnostic command. |

Bundle survivors include command validation, generator behavior and container
wiring. They are not all presumed equivalent or dismissed as framework noise.
Improve their behavioral tests before attempting to narrow the exclusion again.
The full report is generated locally under var/quality-audit/bundle-resources.

Infection defaults `withUncovered` to false; `Configuration::mutateOnlyCoveredCode()`
returns its inverse. See the pinned source for
[the default](https://github.com/infection/infection/blob/0.33.2/src/Container/Container.php)
and [the configuration](https://github.com/infection/infection/blob/0.33.2/src/Configuration/Configuration.php).
Consequently, the normal run's zero uncovered mutants is not evidence of complete
line coverage. To measure uncovered mutations as well, run:

```bash
XDEBUG_MODE=coverage vendor/bin/infection --with-uncovered --threads=max --show-mutations
```

Three ignore entries were removed after adding behavioral regression tests:

- Missing timestamp with a clock near the Unix epoch must still be rejected.
  This kills the timestamp verifier's previously ignored LogicalOr changes.
- Numeric timestamps currently normalize to an integer before signing. A valid
  signature for timestamp `1e3` uses `1000` in the signed input. This kills the
  ignored CastInt mutation in verify without changing the existing input contract.
- An explicit null CSV encoding must preserve bytes even if the process uses a
  different internal encoding. This kills the previously ignored LogicalAnd change.

The default `make ci` run passed style, static analysis, 783 tests with 2,163
assertions, and mutation testing. Additional audit documentation is checked by
the documentation suite after writing; its data-provider case count may increase.

No production behavior or MSI threshold was changed. Other exclusions remain
subject to the limitations documented in [QUALITY](advanced/QUALITY.md).

## Negative checks and documentation

In an isolated temporary copy, QualityConfigTest rejects all four injected faults:
wrong thresholds, a nonexistent Infection exclusion, a nonexistent PHPUnit
exclusion and CLI threshold overrides. Result: **4 tests, 4 failures**, exit 1.
The real configuration retains passing checks. This is new negative evidence,
not a claim about the original historical TDD sequence.

DocumentationImportsTest now reports **file, line and class**. An isolated
Markdown fixture with an invalid import on line 4 fails with that exact location.
The real documentation remains in the normal suite.

The existing link convention is retained and documented in CONTRIBUTING:
navigable Markdown links resolve from their document; backtick path mentions
resolve from the repository root. The canonical agent guide remains CLAUDE.md.
This is an explicit adaptation of B1.5, not a recreation of the obsolete agent folder.

## Remaining evidence

- Separate ErrorClassifier architecture fix, then Deptrac activation in CI.
- Remote CI and demo contract on the commit containing these quality changes.
- The deliberately broken PHP 8.2 generator job and demo-contract job requested
  by B1.3/B2.2. Local fixtures above do not substitute for those remote checks.
- Full review of the remaining mutation exclusions, including globally disabled
  UnwrapArrayMap/UnwrapArrayFilter and connection-key casting assumptions.

No release, deployment or remote deliberately failing branch was created by this audit.
