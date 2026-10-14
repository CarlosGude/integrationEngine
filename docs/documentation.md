# IntegrationEngine — Documentation

This is the **single index for current documentation**. It describes the code on `main`; the latest tagged release is [v9.3.0](https://github.com/CarlosGude/integrationEngine/releases/tag/v9.3.0) and release history remains in [`CHANGELOG.md`](../CHANGELOG.md).

The documentation is deliberately split by responsibility. If two pages appear to answer the same question, prefer the page listed here as the canonical source for that topic.

## Start here

| Need | Canonical document |
|---|---|
| Read the bundle end to end | [developer-guide.md](./developer-guide.md) |
| Understand the architecture and dependency rules | [architecture.md](./architecture.md) |
| Build the first action | [getting-started/actions.md](./getting-started/actions.md) |
| Resolve path/query inputs | [getting-started/context-and-path.md](./getting-started/context-and-path.md) |
| Configure auth/token caching | [getting-started/authorization.md](./getting-started/authorization.md) |
| Map responses and headers | [getting-started/mappers-and-responses.md](./getting-started/mappers-and-responses.md) |
| Send batches | [getting-started/batch-requests.md](./getting-started/batch-requests.md) |
| Choose/customize clients, base URLs or connections | [advanced/architecture/clients.md](./advanced/architecture/clients.md) |
| Add request/client middleware | [architecture.md](./architecture.md#middleware-boundaries) |
| Configure retries/timeouts | [resilience.md](./resilience.md) |
| Configure host/private-network protection | [security.md](./security.md) |
| Receive webhooks | [webhook.md](./webhook.md) |
| Observe lifecycle events | [lifecycle.md](./lifecycle.md) |
| Configure helper observability | [observability.md](./observability.md) |
| Debug an integration | [advanced/debugging.md](./advanced/debugging.md) |
| Enable PHPStan integration rules | [phpstan.md](./phpstan.md) |
| Run/extend the test suite | [testing.md](./testing.md) |
| Check quality gates and measured evidence | [advanced/quality.md](./advanced/quality.md) |
| Contribute | [CONTRIBUTING.md](../CONTRIBUTING.md) |

## Browse by area

- [Getting started](./getting-started/README.md)
- [Advanced topics](./advanced/README.md)
- [Architecture decisions](./adr/)

## Mental model

The engine has two configuration scopes:

1. **Bundle configuration** (`config/packages/integration_engine.yaml`) wires each integration: base URL, client type/service, headers, cache, transport options, middleware and optional connection resolver.
2. **Integration YAML** defines actions and optional webhook metadata: action class, method, path, body class, authorization, cache TTL, action timeout and webhook mapping/signature rules.

At runtime, application code normally calls a small integration facade. The facade obtains an `IntegrationEngine` from `IntegrationRegistry`, calls `send()` or `sendMany()`, and returns integration DTOs. A Gateway/ACL then translates those DTOs into domain concepts when required.

## Current public flow

```text
application facade
    ↓
IntegrationRegistry
    ↓
IntegrationEngine
    ├─ ConfigPort / YamlConfigAdapter
    ├─ optional ConnectionResolverInterface
    ├─ authentication + token cache
    ├─ client middleware
    ├─ request middleware
    ├─ built-in/custom ClientInterface
    └─ ResponseBuilder → AbstractMapper → ResponseInterface
```

Batch requests use the same contracts. Each item is prepared independently; failures are returned as `BatchResult` values instead of aborting the whole batch. Built-in REST, GraphQL and form transports can dispatch concurrently, but built-in request middleware forces a sequential fallback because middleware may inspect or replace a completed response.

## Specialized references

These pages are narrow references, not alternative manuals:

- [form-bodies.md](./form-bodies.md) — how form encoding is selected.
- [resilience.md](./resilience.md) — transport retry/timeout semantics.
- [security.md](./security.md) — outgoing host/private-network policy.
- [webhook-compatibility-notes.md](./webhook-compatibility-notes.md) — webhook contract background and compatibility notes; the usage guide remains `webhook.md`.
- [phpstan.md](./phpstan.md) — optional development-time contracts.

## Architecture decisions

[`adr/`](./adr/) records *why* the project chose its boundaries. ADRs are not usage guides and some intentionally describe superseded decisions. Their status table is the source of truth for which decisions remain active.

## Upgrade guides

Upgrade guides are historical migration documents. They intentionally mention APIs that no longer exist:

- [upgrade-4.0.md](./upgrade-4.0.md)
- [upgrade-5.0.md](./upgrade-5.0.md)
- [upgrade-5.1.md](./upgrade-5.1.md)
- [upgrade-6.0.md](./upgrade-6.0.md)
- [upgrade-7.0.md](./upgrade-7.0.md)
- [upgrade-8.0.md](./upgrade-8.0.md)

For current behavior, use the guides in the sections above, not an old upgrade document.

## Archived material

[`archived/`](./archived/) contains implementation plans, old release preparation, maintenance notes and spikes. They are retained as decision history only and **must not be used as current API documentation**.
