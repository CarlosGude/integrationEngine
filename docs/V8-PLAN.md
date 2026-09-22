# v8.0 closed implementation scope

Approved 2026-09-22: implement the outstanding design and feature blocks before
one release. Breaking changes are permitted. PHP >=8.2 and the supported Symfony
matrix remain requirements. No additional feature scope is admitted.

## Parallel ownership and dependencies

| Stream | Scope | Integration dependencies |
|---|---|---|
| Transport | Form encoding, request middleware, batch, retries and action timeouts | Root wires configuration and DI; SSRF before final retry integration |
| Webhooks | Symfony spike, definitions, signatures, typed mapping, parser, rejection and generator | Root wires YAML and DI; unknown-event response decision pending spike clarification |
| PHPStan | Inference spike and optional mapper/facade/DTO rules | Root wires package metadata and architectural layers |
| Root | Safe lifecycle events, host policy, configuration, DI and integration checks | Merge shared-file changes sequentially |

## Completion checklist

- [ ] Unified JSON/form request model and mixed batch behavior.
- [ ] Generic webhook contract, authenticated ignore behavior, typed reasons and generator.
- [ ] Optional PHPStan rules; inference only if the spike establishes reliability.
- [ ] Scalar-only observable events, batch/token events and recursive secret checks.
- [ ] Final-host allowlist and private-network transport protection.
- [ ] Declarative retries, Retry-After, timeouts and concurrent batch behavior.
- [ ] Demo migrated to the resulting API and consuming the features.
- [ ] Documentation, migration guide and public claims match implementation.
- [ ] PHP/Symfony matrix, contract, architecture and mutation gates pass.
- [ ] Single v8.0 release, notes, package publication and deployed public checks.

No compatibility facade is required solely to preserve a superseded API. Existing
useful behavior is tested while contracts are replaced. Shared Composer, YAML,
DI, engine and workflow files are integrated by the root stream. Tests can run
in parallel; global formatting and mutation runs are serialized.
