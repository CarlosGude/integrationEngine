# IntegrationEngine — Architecture

This document describes the boundaries enforced by the current code. Usage examples live in the focused guides linked from [`DOCUMENTATION.md`](./DOCUMENTATION.md); this page explains the invariants behind them.

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

## Configuration boundaries

IntegrationEngine intentionally has two different YAML scopes:

```text
bundle configuration
config/packages/integration_engine.yaml
    └─ how this integration is wired

integration configuration
src/.../MyApi.yaml
    └─ which actions/webhooks this integration exposes
```

Bundle configuration accepts, per integration:

- `config_path` (required by the compiler pass);
- `base_url` or `client_service`;
- `client` (`rest`, `graphql`, `form_encoded`, or a tagged custom adapter type);
- `headers`, `cache_service`;
- `connection_resolver`;
- `middlewares`, `request_middlewares`;
- transport `timeout`, `max_duration`, `retry`, `allowed_hosts`, `block_private_networks`.

Integration YAML accepts action entries with `action`, optional `method`, `path`, `body`, `authorization`, `cache_ttl`, and per-action `timeout`, plus an optional top-level `webhooks` block.

Putting a client selector in an action entry or an action class in bundle configuration mixes the two boundaries and is not part of the current contract.

## Outbound request pipeline

For `IntegrationEngine::send()` the current order is:

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
8. ResponseBuilder
   └─ mapper → typed ResponseInterface, or EmptyResponse
9. ResponseMapped event
```

Any exception inside preparation/dispatch/mapping produces `RequestFailed` with scalar metadata and is rethrown. Listener exceptions follow the semantics of the configured dispatcher and are not converted into transport errors.

## Actions are stateless descriptions

An `AbstractAction` contains only the resolved method, path, body, authorization, cache TTL and timeout for one call. Concrete actions declare three static contracts:

```php
public static function getName(): string;
public static function hasResponse(): bool;
public static function mapper(): ?string;
```

Instances are created through `AbstractAction::create()` by the configuration adapter. Application code normally passes runtime values through:

- `ActionBodyInterface` — request payload;
- `ActionContextInterface` — path/query context;
- `RequestHeadersInterface` — caller-supplied headers;
- the `connection` argument — opaque application-specific connection identity.

No mutable request state is stored on the concrete action class.

## Path resolution

`YamlConfigAdapter` preserves the configured path and body. `AbstractAction::getPath()` resolves placeholders exclusively from context. A custom `PathResolvableContextInterface` may resolve the whole path first; returning null delegates to the default context lookup.

Missing or non-scalar context parameters fail explicitly. A body field never supplies a URL parameter, and matching body fields remain in the payload.

Detailed examples: [context-and-path.md](./getting-started/context-and-path.md).

## Mapper invariant

Each response-producing action has one mapper relationship in both directions:

- action `mapper()` returns the mapper class;
- mapper `getAction()` returns the action class.

`ResponseBuilder` and `AbstractMapper::map()` validate this at runtime. Optional PHPStan rules can also validate statically declared pairs. Mappers receive **both** decoded body and response headers:

```php
protected static function transform(
    AbstractAction $action,
    array $response,
    array $headers,
): ResponseInterface;
```

An action with `hasResponse() === false` returns `EmptyResponse`; it still performs the request.

## Client and transport boundaries

`ClientInterface` is the engine-facing transport contract. Built-in protocol adapters are:

- `SymfonyHttpClientAdapter` (`client: rest`);
- `GraphQLClientAdapter` (`client: graphql`);
- `FormEncodedClientAdapter` (`client: form_encoded`).

All three built-ins implement `BatchClientInterface` and `DynamicBaseUrlClientInterface`.

A tagged `ClientAdapterInterface` adds another `client:` type while retaining bundle-managed wiring. `client_service` is different: it injects an application-owned `ClientInterface` directly and therefore owns its own transport behavior. Bundle transport options such as retry/timeout/private-network blocking are rejected with `client_service` because the engine does not control that transport.

## Middleware boundaries

There are two middleware levels because they see different data.

### Client middleware

`AbstractClientMiddleware` receives the action, context and caller headers **before** the adapter builds the final HTTP request. It is suitable for concerns such as caching, tracing around the client call, or policies based on action metadata. It also has `processMany()` for batch-aware behavior.

The bundle always places `CachingMiddleware` outermost. Configured user middleware follows. In debug mode `TracingMiddleware` is added nearest the HTTP adapter.

### Request middleware

`RequestMiddlewareInterface` receives a fully built immutable `Request` containing method, final URL, merged headers, body, encoding and timeout. It is the extension point for OAuth 1.0a/AWS-style signatures and any concern that must see the exact outgoing request.

Built-in REST, GraphQL and form adapters accept request middleware. A custom `client_service` is responsible for its own equivalent behavior.

Because request middleware can synchronously inspect or replace a response, built-in batch adapters fall back to sequential per-item `send()` when request middleware is configured.

## Batch execution

`sendMany()` prepares every `EngineRequest` independently and preserves input keys/order in `BatchResultCollection`.

Preparation failures do not abort other items. Prepared requests are grouped by resolved base URL. Within each group:

- a `BatchClientInterface` gets the whole group;
- otherwise the dispatcher sends items sequentially and captures each exception.

REST, GraphQL and form built-ins dispatch all HTTP handles before consuming responses when no request middleware forces the sequential path. `sendManyOrFail()` still dispatches the complete batch first and throws only when results are unwrapped, in input order.

Dynamic auth is batch-aware: token acquisition can be shared, and items rejected with 401 after using cached credentials are re-prepared with one fresh token retry.

## Runtime connections and dynamic auth

`ConnectionResolverInterface` is an application boundary. The engine does not inspect the opaque `connection` value; it asks the configured resolver for `ConnectionCredentials` containing optional base URL, authorization and stable non-secret `connectionId`.

Dynamic-token cache keys are namespaced by integration + token action + an `xxh128` hash of a discriminator. The discriminator prefers:

1. resolved `connectionId`;
2. the scalar `connection` argument;
3. resolved base URL;
4. empty string when none exists.

The hash keeps the cache key compact; it is **not** a mechanism for making secrets safe. Do not use access tokens or client secrets as connection IDs.

## Transport resilience and egress policy

For bundle-managed clients, the compiler builds the Symfony transport in this order:

```text
http_client
  → optional withOptions(timeout/max_duration)
  → optional NoPrivateNetworkHttpClient
  → optional HostPolicyHttpClient
  → optional RetryableHttpClient
  → protocol adapter
```

`HostPolicy` is also checked by the engine before dispatch. `HostPolicyHttpClient` checks the final URL again, including token requests and URLs modified by request middleware, and disables automatic redirects when an allowlist is active.

See [security-v8.md](./security-v8.md) and [resilience-v8.md](./resilience-v8.md) for behavior and supported-version notes.

## Lifecycle and observability boundary

Events contain scalar metadata only; request/response objects, payloads, tokens and exceptions are not attached. The current event set is:

- `RequestSent`
- `ResponseMapped`
- `RequestFailed`
- `TokenRefreshed`
- `WebhookReceived`
- `WebhookRejected`

The Symfony bundle wires engines to Symfony's `event_dispatcher` when available. `LifecycleEventDispatcher` is a small PSR-14 dispatcher for direct/manual use; `SymfonyEventDispatcherAdapter` can bridge local subscriptions to Symfony.

See [LIFECYCLE.md](./LIFECYCLE.md) for the exact event fields.

## Inbound webhook boundary

Webhooks are provider-agnostic. The bundle owns mechanisms, not Stripe/Shopify/etc. integrations:

```text
integration YAML
  → WebhookDefinition
  → IntegrationWebhookRequestParser
  → signature verifier
  → AbstractWebhookMapper
  → MappedRemoteEvent
  → Symfony Webhook / RemoteEvent transport
  → application consumer/listener
```

The parser verifies the raw body before JSON decoding, requires POST + JSON media type, resolves event type/id through configured dot paths and maps only declared event types. Deduplication and business side effects belong to the consuming application.

See [WEBHOOK.md](./WEBHOOK.md).

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

That boundary is an application architecture choice, not something the bundle can enforce, but it prevents an upstream API schema from leaking across the codebase.
