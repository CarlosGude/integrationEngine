# IntegrationEngine · Roadmap

This roadmap separates shipped behavior from proposals. It intentionally avoids dates for future work.

## Recently shipped

### v8.0.x

- scalar-only lifecycle/webhook observability events;
- YAML-driven generic webhooks with typed mapping;
- built-in form-body support;
- outgoing hostname allowlist and optional private-network blocking;
- declarative timeout/retry transport options;
- optional PHPStan integration rules;
- batch-aware dynamic-token refresh and runtime connection resolution improvements;
- `debug:integration` command and strengthened architecture/quality gates.

The exact breaking changes are in [upgrade-8.0.md](./upgrade-8.0.md) and release history in [`CHANGELOG.md`](../CHANGELOG.md).

## Current branch

[v9.3.0](https://github.com/CarlosGude/integrationEngine/releases/tag/v9.3.0) is the latest tag. Since v9.0.0 (URL parameters resolved exclusively from context) it has added body-type validation against the declared YAML class (v9.1.0), an opt-in profiler payload panel (v9.2.0), and shared `defaults.authorization` with per-action override (v9.3.0). See [the changelog](../CHANGELOG.md) for details and migration guidance.

## Next proposals

The concrete unshipped ideas that remain are documented in [advanced/next-features.md](./advanced/next-features.md):

- optional selection of an application-provided Symfony `HttpClientInterface` transport while retaining built-in protocol adapters;
- a first-party metrics exporter only after its storage/worker model is defined.

These are proposals, not commitments.

## Out of scope

- vendor-specific integrations inside this bundle;
- a plugin registry;
- bundle-owned business workflows;
- a second Messenger/DLQ abstraction;
- webhook exactly-once guarantees;
- application IAM/credential persistence;
- deployment-specific metric storage.

## Delivery policy

A feature becomes current documentation only when its implementation, behavioral tests and integration wiring exist in the repository. Plans and spikes remain under `docs/archived/` once they stop describing current behavior.
