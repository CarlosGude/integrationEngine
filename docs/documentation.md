# IntegrationEngine — Documentation

This is the **single index for current documentation**. It describes the code on `main`; the latest tagged release is [v9.4.0](https://github.com/CarlosGude/integrationEngine/releases/tag/v9.4.0) and release history remains in [`CHANGELOG.md`](../CHANGELOG.md).

Every concept has one canonical page. Other pages link to it instead of repeating it.

## Reading order

1. [README](../README.md) — what the bundle is, installation and a first integration.
2. [Getting started](./getting-started/README.md) — actions, context and paths, authorization, mappers, batches.
3. [Bundle configuration](./configuration.md), [HTTP clients](./advanced/architecture/clients.md), [Middleware and response caching](./middleware.md) and [Errors](./errors.md).
4. [Architecture](./architecture.md) — scope, layers and the request pipeline.
5. The topic you need from the table below.

To read everything in one file, use the single-file editions generated from these pages by `make docs-editions` (do not edit them): [minimal](./documentation-minimal.md), [extended](./documentation-extended.md) or [full](./documentation-full.md), which adds the ADRs and upgrade guides.

## By task

| Need | Canonical document |
|---|---|
| Build an action and its YAML entry | [getting-started/actions.md](./getting-started/actions.md) |
| Resolve path/query inputs | [getting-started/context-and-path.md](./getting-started/context-and-path.md) |
| Configure auth/token caching | [getting-started/authorization.md](./getting-started/authorization.md) |
| Map responses and headers | [getting-started/mappers-and-responses.md](./getting-started/mappers-and-responses.md) |
| Send batches | [getting-started/batch-requests.md](./getting-started/batch-requests.md) |
| Look up a bundle configuration key | [configuration.md](./configuration.md) |
| Choose/customize clients, base URLs or connections | [advanced/architecture/clients.md](./advanced/architecture/clients.md) |
| Send form-encoded bodies | [form-bodies.md](./form-bodies.md) |
| Add client/request middleware or cache responses | [middleware.md](./middleware.md) |
| Handle exceptions | [errors.md](./errors.md) |
| Configure transport retries/timeouts | [resilience.md](./resilience.md) |
| Build an application-level retry loop | [advanced/resilience.md](./advanced/resilience.md) |
| Configure host/private-network protection | [security.md](./security.md) |
| Receive webhooks | [webhook.md](./webhook.md) |
| Observe lifecycle events | [lifecycle.md](./lifecycle.md) |
| Configure helper observability | [observability.md](./observability.md) |
| Debug an integration | [advanced/debugging.md](./advanced/debugging.md) |
| Understand scope, layers and the request pipeline | [architecture.md](./architecture.md) |
| See the main class relationships | [advanced/architecture/class-graph.md](./advanced/architecture/class-graph.md) |
| Enable PHPStan integration rules | [phpstan.md](./phpstan.md) |
| Test an integration / run the test suite | [testing.md](./testing.md) |
| Generate an integration with an AI agent | [advanced/ai-agent-usage.md](./advanced/ai-agent-usage.md) |
| Check quality gates and measured evidence | [advanced/quality.md](./advanced/quality.md) |
| See what is planned | [roadmap.md](./roadmap.md), [advanced/next-features.md](./advanced/next-features.md) |
| Contribute | [CONTRIBUTING.md](../CONTRIBUTING.md) |

## Architecture decisions

[`adr/`](./adr/README.md) records *why* the project chose its boundaries. ADRs are not usage guides and some intentionally describe superseded decisions. Their status table is the source of truth for which decisions remain active.

## Upgrade guides

Upgrade guides are historical migration documents. They intentionally mention APIs that no longer exist:

- [upgrade-4.0.md](./upgrade-4.0.md)
- [upgrade-5.0.md](./upgrade-5.0.md)
- [upgrade-5.1.md](./upgrade-5.1.md)
- [upgrade-6.0.md](./upgrade-6.0.md)
- [upgrade-7.0.md](./upgrade-7.0.md) and [migration-v7-client-registration.md](./migration-v7-client-registration.md)
- [upgrade-8.0.md](./upgrade-8.0.md)
- [upgrade-9.0.md](./upgrade-9.0.md)

For current behavior, use the guides above, not an old upgrade document.

## Archived material

[`archived/`](./archived/README.md) contains implementation plans, old release preparation, quality audits and spikes. They are retained as decision history only and **must not be used as current API documentation**.
