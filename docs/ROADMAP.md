# IntegrationEngine · Roadmap

What is released, what is implemented for the next release, and what still needs work.

Detailed task audit, remaining acceptance criteria and execution dependencies:
[bundle execution plan](./PLAN-STATUS.md) (reviewed 2026-09-22).

## Recently shipped

- **v7.0.2** — CSV zero-row and escaping fixes, with parser regression tests.
- **v7.0.1** — Style, static analysis and compatibility corrections after v7.0.
- **v7.0.0** — Per-integration client registration and middleware wiring.
- **v6.0.0** — Provider-agnostic webhooks; vendor integrations and unused reliability
  scaffolding removed ([ADR 0014](./adr/0014-no-vendor-integrations-in-the-bundle.md)).
- **v5.2.0–v5.4.0** — Lifecycle events, separate timings, observability helpers and Flex recipe.

## Now

Implemented in Unreleased, preparing **v7.1.0**:

- `debug:integration [name]` with text and JSON output.
- Behavioral tests and fixes for form-encoded requests, GraphQL batches,
  observability generation and resilience utilities.
- Webhook validation that verifies signatures before decoding and requires a JSON
  object; regression tests for mapper mismatch and malformed input.
- Tests for sequential REST failure isolation, authorization validation and middleware DI.
- PCOV enabled in the demo contract workflow, with a successful remote run.

Validation checked on 2026-09-22 for commit `6f3107cd98daef8114d05dab9e56dd4979347c9d`:
[CI passed](https://github.com/CarlosGude/integrationEngine/actions/runs/35735588854)
and [demo contract passed](https://github.com/CarlosGude/integrationEngine/actions/runs/35735588893).
Release publication remains pending; subsequent changes require validation on
the final release commit.

Release details and compatibility notes: [v7.1 preparation](./release-7.1.md).
Current maintenance tasks: [maintenance backlog](./roadmap-fix.md).

## Next

The following are **proposals, not shipped features**. Their scope, prerequisites
and acceptance criteria are in [next-features.md](./advanced/next-features.md):

1. Select a Symfony HTTP transport per integration, enabling opt-in private-network
   protection with Symfony's decorator while retaining built-in adapters.
2. An optional PHPStan rule for statically detectable action/mapper mismatches.
3. Define logical-operation metrics and batch semantics before adding a Prometheus exporter.

Lifecycle events and metrics callbacks already exist. The bundle does not yet
provide universal SSRF protection, its own PHPStan rules or a Prometheus exporter.

## Later

Only if concrete application needs justify them:

- Legacy migration examples and test harnesses for incremental adoption.
- Additional adapter examples maintained outside the core bundle.
- Training and design workshop materials.

## Out of scope

- A plugin registry or vendor-specific integrations inside this repository.
- A Messenger bridge, webhook replay or a second dead-letter implementation in the
  bundle; consuming applications own those workflows.
- Application authorization, business rules and deployment-specific metrics storage.

## Delivery policy

Features ship after behavioral tests, quality gates and consuming-app compatibility
checks. Release notes describe completed behavior; proposals do not imply delivery dates.
