# IntegrationEngine — Documentation

This is the **single index for current documentation**. It describes the code on `main`; the latest tagged release is v8.0.3 and release history remains in [`CHANGELOG.md`](../CHANGELOG.md).

The documentation is deliberately split by responsibility. If two pages appear to answer the same question, prefer the page listed here as the canonical source for that topic.

## Start here

| Need | Canonical document |
|---|---|
| Read the bundle end to end | [DEVELOPER-GUIDE.md](./DEVELOPER-GUIDE.md) |
| Understand the architecture and dependency rules | [ARCHITECTURE.md](./ARCHITECTURE.md) |
| Build the first action | [getting-started/actions.md](./getting-started/actions.md) |
| Resolve path/query inputs | [getting-started/context-and-path.md](./getting-started/context-and-path.md) |
| Configure auth/token caching | [getting-started/authorization.md](./getting-started/authorization.md) |
| Map responses and headers | [getting-started/mappers-and-responses.md](./getting-started/mappers-and-responses.md) |
| Send batches | [getting-started/batch-requests.md](./getting-started/batch-requests.md) |
| Choose/customize clients, base URLs or connections | [advanced/architecture/clients.md](./advanced/architecture/clients.md) |
| Add request/client middleware | [ARCHITECTURE.md](./ARCHITECTURE.md#middleware-boundaries) |
| Configure retries/timeouts | [resilience-v8.md](./resilience-v8.md) |
| Configure host/private-network protection | [security-v8.md](./security-v8.md) |
| Receive webhooks | [WEBHOOK.md](./WEBHOOK.md) |
| Observe lifecycle events | [LIFECYCLE.md](./LIFECYCLE.md) |
| Configure helper observability | [OBSERVABILITY.md](./OBSERVABILITY.md) |
| Debug an integration | [advanced/debugging.md](./advanced/debugging.md) |
| Enable PHPStan integration rules | [phpstan.md](./phpstan.md) |
| Run/extend the test suite | [TESTING.md](./TESTING.md) |
| Check quality gates and measured evidence | [advanced/QUALITY.md](./advanced/QUALITY.md) |
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

## v8-specific references

These pages are narrow references, not alternative manuals:

- [form-v8.md](./form-v8.md) — how form encoding is selected.
- [resilience-v8.md](./resilience-v8.md) — transport retry/timeout semantics.
- [security-v8.md](./security-v8.md) — outgoing host/private-network policy.
- [webhooks-v8.md](./webhooks-v8.md) — v8 webhook contract and migration notes; the usage guide remains `WEBHOOK.md`.
- [phpstan.md](./phpstan.md) — optional development-time contracts.

## Architecture decisions

[`adr/`](./adr/) records *why* the project chose its boundaries. ADRs are not usage guides and some intentionally describe superseded decisions. Their status table is the source of truth for which decisions remain active.

## Upgrade guides

Upgrade guides are historical migration documents. They intentionally mention APIs that no longer exist:

- [UPGRADE-4.0.md](./UPGRADE-4.0.md)
- [UPGRADE-5.0.md](./UPGRADE-5.0.md)
- [UPGRADE-5.1.md](./UPGRADE-5.1.md)
- [UPGRADE-6.0.md](./UPGRADE-6.0.md)
- [UPGRADE-7.0.md](./UPGRADE-7.0.md)
- [UPGRADE-8.0.md](./UPGRADE-8.0.md)

For current behavior, use the guides in the sections above, not an old upgrade document.

## Archived material

[`archived/`](./archived/) contains implementation plans, old release preparation, maintenance notes and spikes. They are retained as decision history only and **must not be used as current API documentation**.
