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

The exact breaking changes are in [UPGRADE-8.0.md](./UPGRADE-8.0.md) and release history in [`CHANGELOG.md`](../CHANGELOG.md).

## Current branch

[v8.0.4](https://github.com/CarlosGude/integrationEngine/releases/tag/v8.0.4) is the latest tag. It includes the documentation consolidation, a profiler security fix, demo webhook compatibility and CI maintenance. Current `main` is ahead of that tag with landing-page responsive fixes and contract-test CI fixes; those commits do not change the bundle's runtime API. v8.0.2 and v8.0.3 were documentation-only releases.

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
