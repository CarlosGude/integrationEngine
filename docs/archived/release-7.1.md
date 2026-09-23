# v7.1.0 release preparation

Status: draft, not tagged or published. The release is proposed as a minor version
because it adds the `debug:integration` command alongside fixes and tests.
The authoritative change list remains [CHANGELOG.md](../../CHANGELOG.md), under Unreleased.

## Release notes draft

Inspect configured integrations and their actions with:

```bash
php bin/console debug:integration
php bin/console debug:integration my_api --format=json
```

Form-encoded clients are registered automatically and honour runtime URL changes.
The observability generator produces a single observer registration and valid
service wiring. Resilience utilities recognise engine HTTP errors and enforce the
documented retry count, with integer overflow checks on backoff delays.

Webhook validation now checks signatures before decoding JSON and enforces an
object at the root. Regression tests cover malformed payloads, incompatible mappers,
sequential batch failures and invalid authorization/middleware configuration.

## Compatibility notes

- A top-level JSON list was previously accepted by the generic webhook parser
  despite its documented object contract. It now receives HTTP 406; `{}` and
  objects with numeric keys remain accepted. Providers that send webhook batches
  as lists need an application-owned parser designed for that payload contract.
- Malformed signed JSON receives the parser's specific error; a bad signature is
  rejected before decoding. The rejection status remains 406. `Content-Type`
  restrictions have not been added.
- Proposed retry numbers are 1-indexed. The default policy permits three retries;
  zero or negative numbers throw `InvalidArgumentException`. An unrepresentable
  delay throws `OverflowException`. The engine does not enable automatic retries.
- Generated observability services must be instantiated by the application to
  register their observers. Existing generated classes are not rewritten.
- PHP >=8.2 and Symfony ^6.4|^7.0|^8.0 dependency ranges are unchanged.
  PHP 8.4 is used for the dedicated quality and demo contract jobs; it is not
  the bundle's minimum PHP requirement. The SSRF, PHPStan and Prometheus
  proposals are not part of this release.

## Validation and publication

Local quality results are recorded in [QUALITY.md](../advanced/QUALITY.md).
Remote validation checked on 2026-09-22 for commit
`6f3107cd98daef8114d05dab9e56dd4979347c9d`:

- [Main CI passed](https://github.com/CarlosGude/integrationEngine/actions/runs/35735588854),
  including the compatibility matrix, PHP 8.2 generator and mutation gate.
- [Demo contract passed](https://github.com/CarlosGude/integrationEngine/actions/runs/35735588893),
  including tests with PCOV and static analysis against the bundle checkout.

These results validate that commit. Later documentation and release changes
still need validation on the final candidate.

Before publishing:

1. Commit the final release preparation and check the main CI matrix and demo
   contract on that exact commit. A successful earlier run is not sufficient.
2. Confirm that the compatibility notes above cover consuming applications.
3. Move Unreleased entries to `7.1.0` with the actual release date.
4. Create the version tag and release using the repository's release process;
   verify Packagist receives it.

The implementation commit has passed remote validation. Tagging, release
publication and Packagist verification remain pending.
