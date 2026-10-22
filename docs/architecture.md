# IntegrationEngine — Architecture

This document describes the boundaries enforced by the current code: what the bundle owns, how the source is layered and the order in which a request flows. Each runtime concern is explained in its own guide, linked from the pipeline below.

## Scope

The bundle standardizes integration mechanics, not business meaning. It owns:

- integration registration and lookup, and declarative action configuration in YAML;
- action construction, path resolution and response mapping;
- the built-in REST, GraphQL and form-encoded transports, with timeouts, retries and host/network restrictions;
- static and dynamic authorization, token caching and runtime connection resolution;
- client and request middleware, raw-response caching and batch execution;
- scalar lifecycle events, profiler integration and debugging commands;
- generic authenticated webhook parsing with typed mapping;
- generators and optional PHPStan rules.

It deliberately does not own business workflows or domain rules, vendor-specific SDK behavior, credential storage, exactly-once webhook processing, queue/retry/DLQ topology, metrics storage, business-level fallbacks or circuit breakers, or domain entities. When a concern belongs to the consuming application, the bundle exposes a boundary instead of absorbing it.

## Repository layers

Deptrac classifies six source layers plus tests/external tool layers:

| Source layer | Responsibility | May depend on |
|---|---|---|
| `Core` | contracts, value objects, orchestration and framework-independent policy | PSR contracts only |
| `Infrastructure` | HTTP/cache/webhook/lifecycle adapters | Core, PSR, Symfony |
| `Bundle` | Symfony DI, compiler passes, commands and generators | Core, Infrastructure, PSR, Symfony |
| `Utils` | standalone utilities | nothing from the other source layers |
| `Compatibility` | deprecated public facades retained for BC | Core, Infrastructure |
| `Extension` (`src/PHPStan`) | optional PHPStan rules | Core, PHPStan, PhpParser |

`Tests` may depend on all of the above. External collectors (`Symfony`, `Psr`, `PHPStan`, `PhpParser`, `PHPUnit`) exist so Deptrac can reject unclassified or forbidden dependencies.

The important invariant is simple: **Core does not know Symfony**. Transport/framework behavior is pushed outward.

The two configuration scopes — bundle configuration and integration YAML — are described in [configuration.md](./configuration.md).

## Outbound request pipeline

For `IntegrationEngine::send()` the order is:

```text
1. ConfigPort::getAction(actionName, body)
   └─ instantiate body declared by YAML, preserving its fields
2. ConnectionResolver (optional)
   ├─ base URL override
   ├─ authorization override
   └─ connectionId/cache discriminator
3. RequestSent event
4. HostPolicy check on the resolved URL
5. Dynamic auth resolution when configured
   ├─ token cache lookup
   ├─ token action on cache miss
   └─ one fresh-token retry when a cached token gets 401
6. Client middleware pipeline
7. Built-in/custom client
   ├─ final path resolution from context
   ├─ request headers/auth
   ├─ request middleware on the fully built request
   └─ HTTP transport
8. ResponseReceived event
9. ResponseBuilder
   └─ mapper → typed ResponseInterface, or EmptyResponse
10. ResponseMapped event
```

Any exception inside preparation/dispatch/mapping produces `RequestFailed` with scalar metadata and is rethrown. Listener exceptions follow the semantics of the configured dispatcher and are not converted into transport errors.

Where each step is documented:

| Step | Guide |
|---|---|
| 1, 7 (path) | [Actions](./getting-started/actions.md), [Context and path resolution](./getting-started/context-and-path.md) |
| 2 | [Runtime connection resolution](./advanced/architecture/clients.md#runtime-connection-resolution) |
| 3, 8, 10 | [Lifecycle events](./lifecycle.md) |
| 4 | [Security](./security.md) |
| 5 | [Authorization](./getting-started/authorization.md) |
| 6, 7 (request middleware) | [Middleware](./middleware.md) |
| 7 | [HTTP clients](./advanced/architecture/clients.md) |
| 9 | [Mappers and responses](./getting-started/mappers-and-responses.md) |

`sendMany()` applies the same steps per item; see [Batch requests](./getting-started/batch-requests.md). Inbound webhooks have their own flow, described in [webhook.md](./webhook.md#request-flow).

## Transport composition

For bundle-managed clients, the compiler builds the Symfony transport in this order:

```text
http_client
  → optional withOptions(timeout/max_duration)
  → optional NoPrivateNetworkHttpClient
  → optional HostPolicyHttpClient
  → optional RetryableHttpClient
  → protocol adapter
```

See [security.md](./security.md) and [resilience.md](./resilience.md) for behavior and supported-version notes.

## Compatibility layer

Deprecated resilience names remain under `src/Compatibility/` so their public namespaces can survive without pulling Symfony dependencies into Core. They are explicitly classmapped rather than discovered as normal bundle services.

This is an outer compatibility shell, not a place for new behavior.

## Domain boundary

Integration DTOs model the external API. They are not domain entities. A consuming application that has a domain model should translate them at its own boundary:

```text
Controller/Application Service
        ↓
Domain-facing Gateway / ACL
        ↓
Integration facade
        ↓
IntegrationEngine
        ↓
External API DTOs
```

That boundary is an application architecture choice, not something the bundle can enforce, but it prevents an upstream API schema from leaking across the codebase. See [ADR 0003](./adr/0003-gateway-acl-outside-the-bundle.md).

For a diagram of the main classes, see [class-graph.md](./advanced/architecture/class-graph.md).
