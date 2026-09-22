# Quality audit — 2026-09-22

This records the quality work following the [bundle plan](PLAN-STATUS.md).
The implementation baseline is `6f3107cd98daef8114d05dab9e56dd4979347c9d`;
measurements below also include the local quality changes. They are not remote
CI results for a new commit.

## Architecture correction and activation

The initial run found two Symfony dependencies in Core/Resilience/ErrorClassifier.
The separate [PR #5](https://github.com/CarlosGude/integrationEngine/pull/5) introduces
Core-owned classification and backoff contracts, a Symfony Infrastructure adapter
and explicitly classmapped legacy compatibility facades. Existing public methods,
constructor arguments and service IDs remain callable; existing behavior tests
pass unchanged. See [ADR 0015](adr/0015-resilience-classification-boundary.md).

The architecture PR passed [main CI](https://github.com/CarlosGude/integrationEngine/actions/runs/35743555351)
and [demo contract](https://github.com/CarlosGude/integrationEngine/actions/runs/35743555349).
Deptrac now reports zero violations and zero uncovered dependencies. The quality
follow-up adds it to make ci and a dedicated PHP 8.4 workflow job, without a
baseline or skipped violations. Core is still limited to PSR dependencies.

An isolated negative fixture placing a Symfony HttpClientInterface dependency in
Core exits 1 with a forbidden-layer violation. An unclassified dependency is also
required to fail through --fail-on-uncovered. This is a real gate, not a report-only job.

## Final review

Local `make ci` passed: **796 tests, 2,238 assertions**, PHPStan max, style,
Deptrac with zero violations/uncovered dependencies, and Infection with all default
mutators. **1,151 mutations: 1,104 killed, 1 errored, 31 escaped, 15 uncovered**;
MSI **96.00%**, covered MSI **97.27%**. Documentation counts reflect that execution.


All default mutators are enabled and every ignore entry has been removed.
The standard local/CI command now includes --with-uncovered, so uncovered code
reduces MSI. Thresholds remain 85/95. The earlier experiments below are retained
as history, not current totals.

The new scalar-connection regression test covers the formerly suppressed CastString
mutation. Core backoff defaults have their own behavioral test instead of relying
only on callers that explicitly pass constructor arguments.

The final Bundle/Resources-only experiment with all default mutators generated
1,809 mutations: 1,651 killed, 2 errors, 1 syntax error, 121 escaped and 34 uncovered.
Covered MSI was 93.18%; Bundle remains excluded as B1.1 explicitly permits.
The compatibility layer is included in mutation testing.

## Earlier mutation measurements and decisions

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

That first pass changed no production behavior or MSI threshold. The subsequent
architecture correction preserves the documented utility behavior; all mutator
suppressions have now been removed. See [QUALITY](advanced/QUALITY.md).

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

## Remote negative evidence

Temporary commit `4e1fc03` changed the generator to emit a typed class constant
and renamed IntegrationEngine::send(). It was isolated in
[PR #6](https://github.com/CarlosGude/integrationEngine/pull/6), closed without merge.

- [PHP 8.2 generator job](https://github.com/CarlosGude/integrationEngine/actions/runs/35743779152/job/106799963171):
  installation and generation succeeded; the lint step failed in AcmeIntegration.php
  line 11, with `unexpected identifier "NAME", expecting "="`. Exit code 124.
- [Demo contract job](https://github.com/CarlosGude/integrationEngine/actions/runs/35743779469/job/106799970462):
  dependency installation succeeded; demo tests failed with
  `Call to undefined method IntegrationEngine\Core\IntegrationEngine::send()`
  at MovieCatalogGateway.php line 67. This was an API regression, not a runner failure.

The positive architecture commit `7a57e84` passed both workflows. These negative
results fulfill the requested B1.3/B2.2 evidence; the broken branch is never a
release candidate.

## Quality closure: remote verification

The implementation commit `d6c9fb4` in
[PR #7](https://github.com/CarlosGude/integrationEngine/pull/7) passes
[CI](https://github.com/CarlosGude/integrationEngine/actions/runs/35745075378)
and the [demo contract](https://github.com/CarlosGude/integrationEngine/actions/runs/35745075551).
All 14 compatibility matrix cells, the PHP 8.2 generator, style, static analysis,
Deptrac, landing tests and mutation testing passed. SonarCloud and the Cloudflare
Workers check also passed. Remote mutation counts match the local measurement:
1,151 generated, 1,104 killed, one error, 31 escaped and 15 uncovered; MSI 96.00%
and covered MSI 97.27%. These results close the quality review; merging the PR
is a separate repository action. The follow-up documentation commit only records
this evidence and is checked by the same workflows.
