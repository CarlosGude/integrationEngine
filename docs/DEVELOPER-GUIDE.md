# IntegrationEngine Complete Developer Guide

> **Canonical long-form guide.** This document explains IntegrationEngine as one coherent system and is intended to be read sequentially. For task-oriented lookup, use [`DOCUMENTATION.md`](./DOCUMENTATION.md). When this guide and the implementation disagree, the implementation and tested contracts are authoritative.

This guide describes the bundle as one coherent system. Upgrade material and project history are intentionally kept outside this document.

**Recommended reading path:** Chapters 1-6 establish the model and public contracts. Chapters 7-18 explain runtime behavior. Chapters 19-24 cover extension, operations and quality. Chapter 25 collects production caveats and sharp edges. Chapter 26 provides end-to-end recipes; the appendices are reference material.

---

# 1. Purpose and mental model

IntegrationEngine is a Symfony bundle for external API integrations. Its goal is not to hide HTTP; its goal is to make every external integration follow the same predictable shape so application code does not accumulate a different client architecture for every provider.

The central idea is that an integration is a collection of named operations. Each operation is represented by an action. Runtime values are supplied separately through a body, context, headers and optional connection. Transport details, authentication, caching, batching, mapping and observability are handled by reusable infrastructure.


## 1.1 The public flow

```text
application service / facade
    -> IntegrationRegistry
        -> IntegrationEngine
            -> ConfigPort / YamlConfigAdapter
            -> optional connection resolution
            -> authentication and token cache
            -> client middleware
            -> request middleware
            -> built-in or custom client
            -> raw response
            -> mapper
            -> typed ResponseInterface
```

Application code should normally depend on a small integration facade of its own, not on HTTP clients and not directly on provider payload arrays. A gateway or anti-corruption layer can then translate integration DTOs into domain concepts when domain isolation matters.


## 1.2 What the bundle owns

- Integration registration and lookup.
- Declarative action configuration in YAML.
- Action construction and path resolution.
- Built-in REST, GraphQL and form-encoded transports.
- Static and dynamic authorization.
- Dynamic-token caching and one stale-token retry after HTTP 401.
- Runtime connection resolution for multi-tenant or multi-account integrations.
- Client middleware and full-request middleware.
- Optional raw-response caching.
- Batch execution with per-item isolation.
- Transport timeouts, retry decoration and host/network restrictions.
- Response mapping and mapper/action invariants.
- Scalar lifecycle events, Symfony profiler integration and debugging commands.
- Generic authenticated webhook parsing and typed webhook mapping.
- Generators for integrations, webhooks and observability scaffolding.
- Optional PHPStan rules for architectural contracts.

## 1.3 What the bundle deliberately does not own

- Business workflows or domain rules.
- Provider-specific SDK behavior or vendor-specific domain abstractions.
- Persistent credential storage or IAM.
- Exactly-once webhook processing or durable webhook idempotency.
- Application queue topology, retry transports or DLQ policy.
- Metrics storage/export backends.
- Business-level fallbacks, circuit breakers or application orchestration.
- Domain entities. Integration DTOs should remain at the integration boundary.
**Design principle: **The bundle standardizes integration mechanics, not business meaning. When a concern belongs to the consuming application, IntegrationEngine exposes a boundary rather than absorbing the concern into the bundle.


# 2. Installation and Symfony integration


## 2.1 Requirements

| Requirement | Supported |
| --- | --- |
| PHP | 8.2 or newer |
| Symfony Console / DI / HttpClient / YAML / HttpFoundation | 6.4, 7.x or 8.x |
| PSR logging | psr/log 2 or 3 |
| PSR clock | psr/clock 1.x |
| PSR event dispatcher | psr/event-dispatcher 1.x |


## 2.2 Composer installation

```bash
composer require carlosgude/integration-engine
```

After installation, verify that the bundle is registered in config/bundles.php. Depending on how Symfony Flex discovers the bundle in the consuming project, manual registration may be necessary:

```text
return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    IntegrationEngine\Bundle\IntegrationEngineBundle::class => ['all' => true],
];
```

**Why verify registration: **The bundle class lives under IntegrationEngine\Bundle. A consuming application should treat config/bundles.php as the authoritative check that the bundle is actually active.


## 2.3 Minimal bundle configuration

```yaml
# config/packages/integration_engine.yaml
integration_engine:
    integrations:
        acme:
            base_url: 'https://api.example.com'
            config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/Acme/Acme.yaml'
```


## 2.4 Minimal integration YAML

```text
GetEmployee:
    action: App\Infrastructure\Integrations\Acme\GetEmployee\Request\GetEmployeeAction
    method: GET
    path: /employees/{id}
```

Bundle configuration decides how an integration is wired. Integration YAML describes which actions and webhooks the integration exposes. Keeping these two configuration scopes separate is a core part of the design.


# 3. Architecture and package boundaries


## 3.1 Layers

| Layer | Responsibility | Allowed dependencies |
| --- | --- | --- |
| Core | Contracts, orchestration, events, registry, security policy, batch model | Core + PSR |
| Infrastructure | HTTP adapters, cache adapter, middleware, profiler, Symfony lifecycle bridge, webhook parser | Core + PSR + Symfony |
| Bundle | Symfony DI, compiler pass, commands, generators, service wiring | Core + Infrastructure + PSR + Symfony |
| Compatibility | Public resilience compatibility facades | Core + Infrastructure |
| Utils | Standalone helpers such as CSV parsing | No architectural dependencies |
| PHPStan extension | Static-analysis rules | Core + PHPStan + PhpParser |

Deptrac enforces these boundaries. Core cannot depend on Symfony classes. That rule is important: the central integration model remains framework-light even though the bundle wiring and built-in infrastructure are Symfony-aware.


## 3.2 Main runtime collaborators

- IntegrationRegistry: name -> IntegrationEngine lookup.
- IntegrationEngine: orchestration entry point for send(), sendMany() and sendManyOrFail().
- ConfigPort: action and webhook configuration abstraction; YamlConfigAdapter is the built-in implementation.
- ClientInterface: raw request execution abstraction.
- CachePort: token/response cache abstraction.
- AuthenticationHandler / DynamicAuthHandler: dynamic-token orchestration.
- ConnectionResolver: runtime connection override resolution.
- ResponseBuilder: no-response handling and mapper invocation.
- BatchDispatcher: grouping, dispatch and retry orchestration for batches.

## 3.3 Immutability and stateless actions

Concrete actions do not store request-specific state. AbstractAction is constructed through a final static create() method and stores method, path, body, authorization, cache TTL and timeout as readonly data. Concrete action classes only declare stable metadata: getName(), hasResponse() and mapper().

This prevents per-call values from leaking into shared service state and makes an action value safe to pass through middleware, retry and batch processing.


# 4. Configuration model


## 4.1 Bundle configuration keys

| Key | Meaning |
| --- | --- |
| config_path | Path to the integration YAML. Required at compile time. |
| base_url | Base URL used by bundle-managed adapters. Required unless client_service is used. |
| client | Adapter type: rest, graphql, form_encoded, or a registered custom type. Default: rest. |
| client_service | Custom ClientInterface service. Overrides client/base transport creation. |
| timeout | Default transport timeout for the integration. |
| max_duration | Maximum overall transport duration supported by Symfony HttpClient. |
| retry | Symfony transport retry policy configuration. |
| allowed_hosts | Optional hostname allowlist, supports exact names and *.example.com patterns. |
| block_private_networks | Wrap transport with Symfony NoPrivateNetworkHttpClient. |
| cache_service | CachePort service. Default is PSR-6 over Symfony cache.app. |
| connection_resolver | ConnectionResolverInterface service for per-call connection data. |
| middlewares | Ordered AbstractClientMiddleware service IDs, outermost first. |
| request_middlewares | Ordered RequestMiddlewareInterface service IDs for the fully-built request. |
| headers | Default integration headers. Header keys keep their original spelling. |


### Transport ownership with client_service

When client_service is supplied, the engine does not construct the HTTP transport. Configuration rejects retry, timeout, max_duration and block_private_networks with client_service because the bundle cannot guarantee those transport behaviors. A custom client also owns request construction and must implement optional capabilities itself if it wants dynamic base URLs, batching or request middleware semantics.


## 4.2 Action YAML keys

| Key | Meaning |
| --- | --- |
| action | Concrete AbstractAction class. Required. |
| method | HTTP method. Defaults to POST. |
| path | Raw path/template. Defaults to /. |
| body | ActionBodyInterface class accepted by this action. |
| authorization | Static or dynamic authorization definition. |
| cache_ttl | Optional raw-response cache TTL in seconds. |
| timeout | Optional action-level timeout. |
| webhooks | Top-level section, not an action key; defines generic inbound webhook handling. |


## 4.3 Validation timing

Bundle configuration is normalized by Symfony configuration and compiler-pass wiring. Integration YAML is parsed by YamlConfigAdapter when the service is created. Missing files, invalid action definitions, invalid timeouts and malformed webhook definitions fail explicitly.


# 5. Integration facades and registry

Every configured integration is registered in IntegrationRegistry under its configured name. The recommended application-facing pattern is a dedicated facade implementing IntegrationName and declaring a non-empty NAME constant.

```php
final class AcmeIntegration implements IntegrationName
{
    public const NAME = 'acme';

    public function __construct(private IntegrationRegistry $registry) {}

    public function employee(int $id): GetEmployeeResponse
    {
        $response = $this->registry->get(self::NAME)->send(
            GetEmployeeAction::getName(),
            DefaultActionContext::create(['id' => $id]),
        );

        if (!$response instanceof GetEmployeeResponse) {
            throw new LogicException('Unexpected response type.');
        }

        return $response;
    }
}
```

The registry refuses blank names and the marker constant value __MUST_OVERRIDE__. This prevents accidental registration under the interface default.

**Boundary: **A facade is the ideal place to present business-meaningful method names and return types while hiding action-name strings and generic engine APIs from application services.


# 6. Actions, bodies, contexts and path resolution


## 6.1 AbstractAction contract

| Member | Purpose |
| --- | --- |
| getName() | Stable action name used by YAML/config lookup and lifecycle metadata. |
| hasResponse() | Whether ResponseBuilder must map a response. |
| mapper() | Mapper class for response actions, otherwise null. |
| getMethod() | Resolved HTTP method. |
| getRawPath() | Path after body-sourced placeholder substitution but before context resolution. |
| getPath(context) | Final path after custom/default context resolution. |
| getBody() | Resolved body object. |
| getAuthorization() | Current authorization config; may be replaced by connection/dynamic auth. |
| getCacheTtl() | Response-cache TTL or null. |
| getTimeout() | Action timeout or null. |


## 6.2 Action bodies

ActionBodyInterface is a small immutable-data contract: create(array) and toArray(). The YAML body class determines whether a body is accepted. Passing a body to an action that does not declare body is an error. If the action declares a body and the caller passes none, the body class is created with an empty array.


### FormEncodedBodyInterface

A body implementing FormEncodedBodyInterface tells the REST adapter to serialize toArray() as application/x-www-form-urlencoded. Alternatively an entire integration can use client: form_encoded, which makes form encoding the default body encoding for REST-like requests.


### GraphQLBodyInterface

A GraphQL action body implements getQuery() and getVariables(). The GraphQL adapter sends a POST body shaped as {query, variables}. A non-GraphQL body used with the GraphQL client fails with RequestResponseException.


## 6.3 Contexts

ActionContextInterface holds per-call values that are not request-body fields. DefaultActionContext is a generic array-backed implementation. PathResolvableContextInterface adds resolvePath(string): ?string for optional/computed query strings or custom path construction.


## 6.4 Two-stage placeholder resolution

1. YamlConfigAdapter first scans the YAML path for {name} placeholders and substitutes matching scalar keys from the body.
1. Every body key consumed by a placeholder is removed from the body before dispatch, so identifiers are not duplicated into the payload.
1. Any placeholders still present are resolved later from the context by AbstractAction::getPath().
1. A PathResolvableContextInterface may return a complete path. Returning null falls back to standard placeholders; returning an empty string is an error.
```text
# YAML
UpdateEmployee:
    action: App\...\UpdateEmployeeAction
    method: PUT
    path: /employees/{id}
    body: App\...\UpdateEmployeeBody

# Runtime body
['id' => 42, 'name' => 'Ada']

# Result
PUT /employees/42
body: {'name': 'Ada'}
```

**Validation:** Placeholder values from body or context must be scalar. Missing context placeholders, non-scalar values, PCRE failures and empty custom paths produce PathResolutionException.


## 6.5 Request headers

RequestHeadersInterface supplies per-call headers. Header precedence for built-in adapters is: adapter defaults -> integration headers -> authorization headers -> caller request headers. Therefore explicit caller headers win.


# 7. Mapping and response contracts


## 7.1 ResponseInterface

Every mapped response implements ResponseInterface::toArray(). The bundle does not prescribe DTO properties; consuming integrations define their own immutable response classes.


## 7.2 Mapper invariant

AbstractMapper requires getAction() and transform(). map() is final and verifies that the concrete action class exactly equals getAction(). ResponseBuilder performs the same invariant before invoking the mapper. An action and mapper therefore form a one-to-one declared pair.

```php
final class GetEmployeeMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return GetEmployeeAction::class;
    }

    protected static function transform(AbstractAction $action, array $response, array $headers): ResponseInterface
    {
        return new GetEmployeeResponse(
            id: (int) $response['id'],
            name: (string) $response['name'],
            requestId: $headers['x-request-id'][0] ?? null,
        );
    }
}
```

Mapper transform() receives both the decoded body and response headers. This lets integrations map pagination headers, rate-limit data, tracing IDs or other metadata without exposing the raw HTTP response object.


## 7.3 No-response actions

If hasResponse() is false, ResponseBuilder returns EmptyResponse and does not require a mapper. If hasResponse() is true but mapper() returns null, NotMappedActionException is thrown.


## 7.4 Sharing mapping logic

Two different action classes cannot share one mapper class because the mapper/action invariant checks concrete classes. Shared transformation logic should be extracted into a helper, trait or domain-specific transformer that each mapper calls.


# 8. HTTP clients and request construction


## 8.1 Client capabilities

| Capability | Contract | Effect |
| --- | --- | --- |
| Basic send | ClientInterface | Execute one action and return body, response headers and optional status code. |
| Batch | BatchClientInterface | Execute keyed PreparedRequest values concurrently where transport permits. |
| Dynamic base URL | DynamicBaseUrlClientInterface | Return a cloned client pointed at a per-call base URL. |
| Named adapter | ClientAdapterInterface | Expose a client type for bundle configuration. |


## 8.2 REST adapter

SymfonyHttpClientAdapter is the default rest adapter. It resolves the action path, merges headers, serializes bodies and converts HTTP/network errors into RequestResponseException.

- Request bodies are sent only for POST, PUT and PATCH.
- JSON is the default body encoding.
- FormEncodedBodyInterface switches an individual request to form encoding.
- Action timeout is passed to Symfony HttpClient for REST/form requests.
- HTTP 204 or an empty body maps to an empty array.
- HTTP status >=400 throws RequestResponseException and includes the upstream body in the exception context.
- Network exceptions are wrapped as RequestResponseException with statusCode 0.

## 8.3 Form-encoded adapter

FormEncodedClientAdapter delegates to the REST adapter with BodyEncoding::Form as the default. It supports dynamic base URLs, batching and request middlewares just like REST.


## 8.4 GraphQL adapter

- Always sends HTTP POST to the configured endpoint URL.
- Requires GraphQLBodyInterface.
- Sends JSON with query and variables.
- Returns only the GraphQL data member to the mapper.
- If the response contains a non-empty errors array, it throws RequestResponseException even when HTTP status is 200.
- GraphQL supports batch concurrency and request middleware.
- Per-action timeout stored on AbstractAction is currently not propagated into GraphQLClientAdapter request options. Use integration-level transport timeout when GraphQL calls need a timeout.
**GraphQL error semantics: **A GraphQL application error can be represented as RequestResponseException with statusCode 200. Code that classifies failures only by HTTP status must account for this.


## 8.5 Custom client types

Implement ClientAdapterInterface and register the service with the integration_engine.client_adapter tag. Its static getClientType() becomes the client: value. Later registrations override earlier ones, so an application adapter can intentionally replace a built-in type.

A custom client may also implement BatchClientInterface and DynamicBaseUrlClientInterface. If it does not implement dynamic base URLs, per-call baseUrl/connection URL values are ignored by the client.


# 9. Authentication and token lifecycle


## 9.1 Static authorization

| type | Required/typical params | Header produced |
| --- | --- | --- |
| bearer | token, optional prefix | Authorization: <prefix or Bearer> <token> |
| basic | username, password | Authorization: Basic <base64(username:password)> |
| api_key | token, optional header, optional prefix | <header or X-Api-Key>: [prefix ]token |

Unknown static auth types fail explicitly. Auth parameter values must be strings when present.


## 9.2 Dynamic authorization configuration

```text
authorization:
    type: dynamic
    action: FetchToken
    token_field: access_token
    ttl: 3600
    header: Authorization       # optional
    prefix: Bearer              # optional
```

DynamicAuthorizationConfig requires an auth action name, token field and non-negative TTL. The token field is read from the auth action response after mapping when the auth action declares a response. token_field is a direct array key, not a dot-path expression.


## 9.3 Token cache key

```text
integration_engine.token.<integration>.<token-action>.<xxh128(connection-discriminator)>
```

The connection discriminator is derived in this order: resolved connectionId, scalar $connection argument, resolved base URL, or empty string. This prevents token sharing across distinct connections when a stable discriminator is available.


## 9.4 Token fetch and retry

1. Look up the token cache entry.
1. If present, convert dynamic auth to static auth and send the business request.
1. If absent, execute the configured token action, map it if necessary, extract token_field, store it for ttl seconds and emit TokenRefreshed(reason=cache_miss).
1. If a request using a token that was already cached receives HTTP 401, delete that cache entry, fetch a fresh token, emit TokenRefreshed(reason=rejected_401), and retry the business request exactly once.
1. A 401 from a token fetched during the current attempt is final. Non-401 failures never invalidate the token.
**Reasoning: **The single retry addresses tokens revoked before their TTL. Re-fetching repeatedly would hide real authentication problems and create loops.


## 9.5 Cache backend

The default CachePort is Psr6CacheAdapter backed by Symfony cache.app. Sharing and persistence therefore depend on the consuming application cache configuration. Do not assume process-local or shared behavior without inspecting that application configuration.


# 10. Runtime connections and multi-tenant integrations

The optional $connection argument to send()/sendMany() is opaque to the engine. The application-provided ConnectionResolverInterface interprets it and returns ConnectionCredentials containing any combination of baseUrl, authorization override and stable connectionId.

```php
final class TenantConnectionResolver implements ConnectionResolverInterface
{
    public function resolve(mixed $connection): ConnectionCredentials
    {
        $tenant = $this->repository->get((string) $connection);

        return new ConnectionCredentials(
            baseUrl: $tenant->apiBaseUrl,
            authorization: new StaticAuthorizationConfig('api_key', [
                'header' => 'X-Api-Key',
                'token' => $tenant->apiKey,
            ]),
            connectionId: $tenant->id,
        );
    }
}
```


## 10.1 Precedence

- An explicit baseUrl argument passed to send()/EngineRequest wins over the resolver baseUrl.
- A resolver authorization overrides the action/YAML authorization.
- connectionId is preferred for token-cache separation; if absent, scalar connection is used; then resolved base URL.

## 10.2 Batch memoization

During sendMany(), scalar connection values are memoized so repeated items for the same connection call the resolver only once. Non-scalar connection objects are not memoized by this mechanism.


## 10.3 Stable connection identifiers

connectionId must be stable and non-secret. It is an isolation identifier, not a credential. Use a tenant/account/connection UUID, never a token, consumer secret or password.


# 11. Middleware pipeline


## 11.1 Two middleware layers

| Layer | Input | Best for |
| --- | --- | --- |
| AbstractClientMiddleware | Action + context + headers before final request construction | Caching, logging, policy, action-level concerns |
| RequestMiddlewareInterface | Fully built Request with method, final URL, headers, body, encoding, timeout | Request signing, final-header manipulation, transport-adjacent concerns |


## 11.2 Action-level ordering

```text
CachingMiddleware (outermost)
    -> configured user middlewares, in declaration order
        -> TracingMiddleware (debug mode only)
            -> HTTP adapter
```

Middleware[0] is outermost: it sees the request first and the response last. CachingMiddleware can short-circuit the entire inner chain. Tracing is inside caching, so cache hits are not timed as HTTP calls.


## 11.3 Request middleware

Request middleware receives the fully built immutable Request. withHeader() returns a new Request. Middleware may mutate the logical request by creating a replacement, short-circuit with a synthetic raw response, or throw. Built-in REST, GraphQL and form adapters support it.

When request middleware is configured, built-in sendMany() falls back to sequential per-item send(). This is intentional because a request middleware may need to observe or replace each completed response.


## 11.4 BaseUrlAwareMiddlewareInterface

Middleware that includes the active base URL in behavior or cache keys can implement BaseUrlAwareMiddlewareInterface. MiddlewareClient::withBaseUrl() clones those middleware instances for the new URL while reusing other middlewares.


## 11.5 Built-in LoggingMiddleware

LoggingMiddleware is optional application middleware. It logs the resolved path, method, response status/duration, and on failure the exception message and class.

**Sensitive-data warning: **Do not assume LoggingMiddleware performs comprehensive PII/secret redaction. The current implementation logs resolved paths and exception messages. Upstream error messages can contain response bodies. Use it only with an appropriate logging policy or replace/decorate it with application-specific redaction.


# 12. Response caching

When an action has cache_ttl, the automatically wired CachingMiddleware caches the raw client response before mapping. A cache hit therefore returns the same raw body/headers/status shape and the mapper still runs normally after the middleware chain returns.


## 12.1 Cache behavior in single requests

1. Read cache using the derived response key.
1. On hit, return the raw cached response and record a zero-duration cached profiler call when the collector is available.
1. On miss, call the inner middleware/client, cache successful raw response for cache_ttl, and return it.

## 12.2 Cache behavior in batches

sendMany() partitions the batch into hits and misses before dispatch. Only misses reach the inner batch client, so concurrent transport is preserved for uncached items. Throwable results are never written to the response cache.


## 12.3 Current response-cache key

```text
ie_response_<integration>_<xxh128(JSON([action class, baseUrl, context array, request headers]))>
```

**Sharp edge - body is not in the key: **The response-cache key currently does not include the action body. Two calls using the same action/context/headers/base URL but different bodies can collide if cache_ttl is enabled. Avoid response caching for body-dependent actions unless another keyed value distinguishes the calls, or provide a custom caching strategy.

**Sharp edge - connectionId/auth are not in the key: **The response cache is namespaced by base URL, not by ConnectionCredentials::connectionId or authorization. Two tenants sharing the same base URL with different credentials can share a cached response when action/context/headers match. For tenant-specific data on a shared endpoint, disable cache_ttl or replace the cache middleware/key strategy.


# 13. Batch execution and concurrency


## 13.1 EngineRequest

EngineRequest packages the same per-call inputs accepted by send(): actionName, context, body, headers, baseUrl and connection. A batch may mix actions, contexts, bodies and connections.


## 13.2 sendMany() guarantees

- Input keys and order are preserved.
- One item failing does not abort unrelated items.
- Every result is represented as BatchResult success or failure.
- Configuration/connection/auth preparation failures are isolated before transport dispatch.
- Clients implementing BatchClientInterface can dispatch concurrently.
- Clients without BatchClientInterface fall back to sequential sends.
- Items targeting different resolved base URLs are grouped and dispatched through appropriately cloned clients.
- All requests are executed before sendManyOrFail() rethrows the first failure in request order.

## 13.3 BatchResultCollection

The collection is read-only, iterable, countable and array-accessible. It exposes keys(), hasFailures(), responses(), errors(), actionClassFor() and mapWith(). BatchResult::response() returns the response or rethrows the stored failure; error() returns the stored Throwable or null.


## 13.4 Batch mapper

AbstractBatchMapper consolidates a homogeneous batch after individual item mapping. getAction() defines the required concrete action class. mapWith() validates every resolved item against that action before calling consolidate(). Preparation failures with no resolved action class are passed through for the batch mapper to decide how to handle.


## 13.5 Concurrent transport semantics

REST and GraphQL dispatch all Symfony HttpClient request handles before consuming any responses. Symfony responses are lazy, so network activity overlaps. The public API is not lazy: sendMany() returns only after the batch consumption and mapping pass completes.


## 13.6 Dynamic auth in batches

Items share dynamic tokens by the same token cache key. A token fetched by an earlier batch item is reused by later items. Only items that entered the batch with a token already cached before the batch are eligible for the one 401 refresh. When several retryable items share one token cache key, the stale cache entry is deleted once and retry preparation reuses the newly fetched token.


# 14. Timeouts, retries and resilience


## 14.1 Integration-level transport controls

| Option | Behavior |
| --- | --- |
| timeout | Passed as a default Symfony HttpClient transport option. |
| max_duration | Passed as an overall transport duration option. |
| retry.max_retries | Maximum RetryableHttpClient retries. Default 3. |
| retry.delay_ms | Initial delay. Default 200 ms. |
| retry.multiplier | Exponential multiplier. Default 2.0. |
| retry.max_delay_ms | Maximum delay. Default 2000 ms. |
| retry.jitter | Delay jitter from 0 to 1. Default 0.1. |
| retry.status_codes | Retryable response codes. Defaults include 423,425,429,500,502,503,504,507,510 plus network code 0. |
| retry.retry_non_idempotent | If false, retries are limited to idempotent HTTP methods. |


## 14.2 Action-level timeout

Action YAML can define timeout. The REST/form path passes that value into the final Request and Symfony HttpClient options, overriding/augmenting transport defaults as Symfony resolves them.

**GraphQL difference: **GraphQLClientAdapter currently constructs its final Request without copying AbstractAction::getTimeout(). Therefore use integration-level timeout for GraphQL if timeout enforcement is required.


## 14.3 Transport retry versus Core resilience policy

Declarative retry in integration_engine.yaml wraps the Symfony HTTP transport with RetryableHttpClient. This is the automatic network/HTTP retry mechanism controlled by the bundle.

Core also exposes ErrorClassifierInterface, ErrorClassification, ResiliencePolicyInterface and ExponentialBackoff. These are decision helpers for application-owned retry/fallback workflows; IntegrationEngine::send() does not automatically execute ResiliencePolicyInterface.


### Error classification

ErrorClassification considers networkError=true, HTTP 408, 429 and 5xx transient. Other 4xx (except 408/429) are permanent. EngineErrorClassifier maps RequestResponseException only by status code.

**Network-classification caveat: **Built-in HTTP adapters wrap transport failures as RequestResponseException(statusCode=0). EngineErrorClassifier does not mark status 0 as networkError, so application-level ExponentialBackoff using EngineErrorClassifier will not classify those wrapped network failures as transient. Declarative Symfony transport retry operates earlier, before wrapping, and is the preferred automatic transport retry path.


# 15. Outgoing security


## 15.1 Host allowlist

allowed_hosts creates a HostPolicy. Exact hostnames and wildcard subdomains such as *.example.com are supported. The engine checks the composed destination before dispatch, and bundle-managed transports can additionally be wrapped by HostPolicyHttpClient so final URLs, including token requests and request-middleware changes, are checked.

When HostPolicyHttpClient is active, max_redirects is forced to 0 so an approved host cannot redirect to an unapproved destination. Follow an approved redirect explicitly as a new guarded request if needed.


## 15.2 Private-network blocking

block_private_networks wraps the transport with Symfony NoPrivateNetworkHttpClient. This is useful for integrations where target URLs or runtime base URLs may be influenced by configuration or tenant data and SSRF protection matters.


## 15.3 Secret boundaries

- ConnectionCredentials::connectionId must be non-secret.
- Lifecycle events carry scalar metadata, not token/request/response objects.
- The profiler stores exception class names, not exception messages or upstream bodies.
- debug:integration deliberately suppresses YAML parser details because source lines might contain credentials.
- Webhook SignatureConfig marks the secret as a sensitive parameter and rejection events expose fixed reason codes.

## 15.4 Logging is a separate risk surface

Application loggers and optional LoggingMiddleware are outside the profiler hardening boundary. If logging resolved paths or exception messages is unacceptable, use a redacting logger/middleware and avoid writing raw provider payloads or credentials.


# 16. Lifecycle events and observability


## 16.1 Event model

| Event | Important fields | When emitted |
| --- | --- | --- |
| RequestSent | integrationName, action, method, raw path, timestamp, connectionId, requestKey | After action/connection resolution and before host policy/dispatch; also emitted with blank method/path if preparation fails early. |
| ResponseMapped | integrationName, action, durationMs, statusCode, responseClass, timestamp, requestKey | After successful response mapping. |
| RequestFailed | integrationName, action, durationMs, statusCode, exceptionClass, safe message, timestamp, requestKey | When preparation, transport or mapping fails. |
| TokenRefreshed | integrationName, token action, reason, timestamp, requestKey | After a token is fetched and cached. |
| WebhookReceived | integrationName, eventType, eventId, timestamp | After signature verification, JSON validation and typed mapping. |
| WebhookRejected | integrationName, fixed reason, timestamp | Before rejecting an invalid webhook. |


## 16.2 Duration semantics

ResponseMapped.durationMs and RequestFailed.durationMs are logical engine durations from the beginning of send/item preparation until mapping/failure. They may include connection resolution, authentication/token fetching, middleware, transport, retry and mapping. They are not a split between HTTP time and mapping time.


## 16.3 Batch requestKey

Batch lifecycle events carry the original input key as requestKey, allowing metrics and traces to correlate an event to one EngineRequest.


## 16.4 Symfony event dispatcher

Bundle-managed engines receive Symfony event_dispatcher when available, so normal #[AsEventListener] listeners are the simplest integration path in a Symfony application.

## 16.5 LifecycleEventDispatcher and ObservabilitySetup

LifecycleEventDispatcher is a small PSR-14 dispatcher with direct subscriptions. SymfonyEventDispatcherAdapter can bridge those local subscriptions to Symfony. ObservabilitySetup registers logging, slow-request warnings, metrics callbacks and error callbacks on a LifecycleEventDispatcher.

**Wiring caveat: **A generated ObservabilitySetup only observes events if it shares the same LifecycleEventDispatcher instance used by the engine. Bundle-managed engines normally dispatch directly through Symfony event_dispatcher, so ordinary Symfony listeners are the recommended default.


# 17. Profiler and debugging


## 17.1 Symfony profiler collector

When kernel.debug is true, the profiler service is available and HttpKernel DataCollectorInterface exists, the compiler pass wires TracingMiddleware and a shared IntegrationEngineDataCollector. All integrations report into that collector for the current application request.

Recorded fields are integration name, action name, HTTP method, raw path template, duration, optional HTTP status, exception class and cache-hit flag. Exception messages, bodies, final resolved URLs, runtime context and credentials are deliberately not stored.


## 17.2 Cache hits

Cache hits never reach TracingMiddleware. CachingMiddleware records them directly with duration 0 and cached=true.


## 17.3 Batch timings in profiler

TracingMiddleware measures one batch wall-clock duration and divides it across the number of requests for profiler aggregation. This avoids counting the full same wall-clock interval once per item. The per-item profiler duration is therefore an accounting approximation, not an independent network latency measurement.


## 17.4 debug:integration

```bash
php bin/console debug:integration
php bin/console debug:integration acme
php bin/console debug:integration acme --format=json
```

Without an argument, the command lists configured integrations, client/service and config path. With an integration name, it parses the YAML and lists action name, method, path and class without sending requests. YAML parse errors are intentionally reported generically to avoid echoing source lines that might contain credentials.


# 18. Inbound webhooks


## 18.1 Responsibility boundary

Webhook support authenticates, validates and maps provider HTTP events. It does not own provider business handlers, durable deduplication, replay tooling, exactly-once guarantees or application queue policy.


## 18.2 Integration YAML shape

```yaml
webhooks:
    type_field: type
    id_field: id
    unknown_events: reject
    signature:
        type: timestamped_hmac
        header: Stripe-Signature
        secret: '%env(STRIPE_WEBHOOK_SECRET)%'
        tolerance: 300
    events:
        payment_intent.succeeded:
            mapper: App\Webhooks\Stripe\PaymentIntentSucceededMapper
```

type_field and id_field are non-empty dot-separated key paths. Every event key must equal the mapper class eventType(). Unknown-event policy is ignore or reject.


## 18.3 Request acceptance

- Only POST requests are accepted.
- Content-Type must be application/json or an application/*+json media type.
- Signature verification happens on the raw request body before JSON decoding.
- The JSON root must be an object; a JSON array is rejected.
- type_field must resolve to a non-empty string.
- id_field must resolve to a non-empty string or integer.

## 18.4 Signature schemes

| type | Algorithm / format |
| --- | --- |
| hmac_sha256 | Hex HMAC-SHA256 over the raw body, optional configured prefix. |
| hmac_base64 | Base64 of the raw binary HMAC-SHA256 digest. |
| timestamped_hmac | Header parts t=<timestamp>, v1=<hex HMAC>; signature is HMAC-SHA256(timestamp + "." + raw body) and timestamp must be within tolerance. |

Signature headers are read according to SignatureConfig. Missing, malformed, invalid and out-of-tolerance cases produce fixed WebhookRejectionReason values.


## 18.5 Mapping

AbstractWebhookMapper mirrors the action mapper invariant. eventType() declares one type, map() is final and validates that declared type before transform(). MappedRemoteEvent stores both the raw decoded payload and the already typed WebhookEventInterface.


## 18.6 Unknown events

With unknown_events=reject, unknown event types are rejected. With ignore, IntegrationWebhookRequestParser returns null. Symfony controller behavior for a null parsed event is not a portable guaranteed 2xx acknowledgement; applications that require explicit acknowledgement of authenticated unknown events should own that controller/transport behavior.


## 18.7 Symfony controller and Messenger

The standard Symfony Webhook controller path uses Symfony Webhook/RemoteEvent and Messenger. The parser itself does not require the application to adopt a bundle-owned Messenger abstraction. An application can call the parser through its own controller/transport when it wants different acknowledgement or queue semantics.


## 18.8 Optional dispatch helper

WebhookEventDispatcher can map a plain RemoteEvent through an AbstractWebhookMapper and dispatch the typed event through Symfony EventDispatcher. ConsumesWebhookEvents packages that pattern for AsRemoteEventConsumer consumers. The generic parser path can also provide MappedRemoteEvent directly with event().


## 18.9 Idempotency

**Application-owned: **The bundle exposes event IDs but does not provide durable idempotency storage. Implement a unique durable receipt around the same transaction as the side effect when duplicate delivery matters. A cache TTL is not an exactly-once guarantee.


# 19. Generators and console commands


## 19.1 make:integration

```bash
php bin/console make:integration Acme GetEmployee
```

The command creates the integration facade/config skeleton when needed, creates action files, mapper/response files when appropriate, and appends the action to the integration YAML. On first setup it can create config/packages/integration_engine.yaml after asking for base URL and client type.

Adapters declare whether path and method are required, so generator prompts adapt to the selected client type. REST/form adapters require both; GraphQL does not require an action path/method declaration in the same way.


## 19.2 make:webhook

```bash
php bin/console make:webhook stripe payment_intent.succeeded --signature-type=timestamped_hmac --signature-header=Stripe-Signature
```

The command generates webhook application classes and merges the integration webhook YAML. Supported signature generator choices are hmac_sha256, hmac_base64 and timestamped_hmac.


## 19.3 make:observability

```bash
php bin/console make:observability acme
```

The command creates an application observability setup and service entry around LifecycleEventDispatcher. Treat the generated service as scaffolding: customize callbacks and ensure the same dispatcher is actually used by the integration engine if you choose this wiring style.


## 19.4 debug:integration

Use the debug command to inspect configuration without making external calls. It supports text and JSON output.


# 20. Extension points and custom implementations


## 20.1 Custom HTTP adapter

Implement ClientAdapterInterface. Choose a unique getClientType(), declare whether path/method are required, and implement send(). Add BatchClientInterface for efficient batches and DynamicBaseUrlClientInterface for per-call URL overrides. Tag the service integration_engine.client_adapter.


## 20.2 Custom client service

Set client_service to any ClientInterface service when the application wants complete transport ownership. Bundle transport options are not applied. If the custom client needs batch or dynamic-base-url behavior, implement the optional interfaces explicitly.


## 20.3 Custom cache

Implement CachePort get/set/delete and configure cache_service. This affects dynamic-token caching and the built-in response cache middleware for that integration.


## 20.4 Custom connection resolver

Implement ConnectionResolverInterface when runtime connection input must be translated into base URL, auth or an isolation identifier.


## 20.5 Custom action middleware

Subclass AbstractClientMiddleware and register/tag it. Override processMany() when the concern must be batch-aware; otherwise the default implementation transparently passes the batch to the next layer.


## 20.6 Custom request middleware

Implement RequestMiddlewareInterface for concerns that require the final URL/body/headers such as OAuth 1.0a or AWS-style signing. Be aware of the sequential batch fallback in the built-in adapters.


## 20.7 Custom webhook signature

Implement SignatureVerifierInterface when none of the three built-in HMAC schemes match the provider. Keep verification on raw bytes and compare secrets with constant-time primitives such as hash_equals.


## 20.8 Custom PHPStan usage

The bundle ships optional static-analysis rules. Projects can include the package PHPStan extension to enforce mapper/action reciprocity, final readonly response/event DTOs and declared object return types for public integration-facade methods.


# 21. Error model and exception taxonomy

IntegrationEngine prefers typed exceptions at boundaries rather than returning error arrays from send(). Batch APIs are the exception: each item stores its Throwable in BatchResult so one failure does not abort the batch.

| Exception | Meaning |
| --- | --- |
| ActionNotFoundException | Requested action name is absent from the integration config. |
| IntegrationNotFoundException | Registry has no configured integration under the requested name. |
| PathResolutionException | Missing/non-scalar placeholder, empty custom resolved path or regex failure. |
| MapperActionMismatchException | Mapper getAction() does not match the concrete action. |
| BatchMapperActionMismatchException | A homogeneous batch mapper received a different action class. |
| NotMappedActionException | Action declares a response but no mapper. |
| DynamicAuthException | Token field missing or non-scalar. |
| ConnectionResolutionException | A non-null connection was passed without a configured resolver. |
| DisallowedHostException | Destination violates host policy. |
| RequestResponseException | HTTP, GraphQL or network request failure; statusCode 0 represents non-HTTP/network wrapping. |
| WebhookMapperMismatchException | Webhook mapper does not declare the event type being mapped. |
| WebhookSignatureException | Signature validation failed with a structured rejection reason. |


## 21.1 RequestResponseException and sensitive data

The exception stores a human-readable context. REST and GraphQL HTTP errors can include the upstream response body in that context; network failures include the transport exception message. Do not persist arbitrary exception messages into security-sensitive telemetry without redaction. Lifecycle RequestFailed and the profiler intentionally use safer bounded metadata instead.


# 22. Static analysis, architecture enforcement and quality


## 22.1 PHPStan rules

| Rule | Enforced contract |
| --- | --- |
| MapperActionRule | Mapper getAction() returns an AbstractAction class and the action mapper() returns that mapper class. |
| ResponseClassModifiersRule | Concrete ResponseInterface and WebhookEventInterface DTO classes are final and readonly. |
| IntegrationFacadeReturnTypeRule | Public methods on IntegrationName facades declare return types; iterable results contain objects rather than untyped scalar collections. |


## 22.2 Deptrac

Deptrac checks both src and tests and enforces the layer rules described in Chapter 3. The intended gate is zero violations, zero skipped violations and zero uncovered dependencies.


## 22.3 Mutation testing

Infection runs default mutators, includes uncovered code and uses minimum MSI 85% and covered MSI 90%. The Bundle layer and small pure contracts are excluded from mutation source according to infection.json5; those exclusions should be understood as explicit scope, not hidden success.


## 22.4 CI coverage

The CI workflow runs style, PHPStan, Deptrac, landing content tests, generator smoke tests, PHPUnit over supported stable PHP/Symfony combinations, and Infection. A separate contract workflow validates the consuming demo application against the bundle checkout.


# 23. Testing strategy


## 23.1 What to test in a consuming integration

- Action class metadata: name, hasResponse(), mapper().
- Body/context/path behavior including missing and non-scalar values.
- Mapper transformation with representative response bodies and headers.
- Facade return types and gateway/ACL translation.
- Authentication failure and token refresh behavior if dynamic auth is used.
- Batch partial failures and key preservation.
- Connection isolation when several tenants/accounts share an endpoint.
- Request-signing middleware against the final URL/body/headers.
- Webhook signature verification, event type/id extraction and typed mapping.
- Security boundaries: no secrets in profiler/lifecycle data.

## 23.2 Bundle testing style

The bundle uses small fake implementations of ports and clients for core behavior, adapter tests around Symfony HttpClient behavior, dependency-injection/compiler-pass tests, documentation link/import tests, PHPStan rule fixtures, quality-config tests, and a separate consuming demo contract suite.


## 23.3 Commands

```bash
make test       # PHPUnit
make stan       # PHPStan
make cs         # PHP-CS-Fixer dry run
make deptrac    # architecture
make mutation   # Infection
make qa         # cs + stan + test
make ci         # qa + deptrac + mutation
```


# 24. Utilities and supporting components


## 24.1 CSV parser

CsvParser is a standalone utility that converts CSV text to associative rows using a header line. CsvParseOptions controls delimiter, enclosure, source encoding, whether empty lines are skipped and which row contains headers.

| Option | Default / behavior |
| --- | --- |
| delimiter | , |
| enclosure | " |
| encoding | UTF-8; other configured encodings are converted to UTF-8 |
| skipEmptyLines | truthy by default |
| headerRow | 0-based header row index |

Row column counts must exactly match the header count. Utility factories provide semicolon-delimited, tab-delimited, UTF-16 and ISO-8859-1 presets. Note the method name semiocolnDelimited() contains the spelling used by the public implementation.


## 24.2 Compatibility resilience facades

The Compatibility namespace exposes resilience compatibility facades outside Core. Prefer the Core resilience contracts/classes when writing new integration code; the separate namespace keeps Core dependency direction clean.


# 25. Operational guidance and sharp edges

The following points are intentionally explicit. They are not reasons to avoid the bundle; they are current implementation boundaries a developer should know before relying on a feature in production.

| Sharp edge | Practical consequence |
| --- | --- |
| Response-cache body omission | cache_ttl keys do not include ActionBodyInterface data. Body-varying cacheable requests can collide. |
| Response-cache tenant isolation | response-cache keys include base URL but not connectionId or authorization. Tenants sharing one endpoint can collide. |
| GraphQL action timeout | per-action timeout is not copied into GraphQL request options; use integration transport timeout. |
| Request middleware and batch concurrency | any configured request middleware makes built-in REST/GraphQL/form batches sequential. |
| Dynamic base URL capability | a custom client that does not implement DynamicBaseUrlClientInterface silently keeps its configured target. |
| LoggingMiddleware sensitivity | it logs resolved paths and exception messages; upstream data may appear in logs. |
| RequestResponseException sensitivity | its context can include upstream response bodies. Prefer lifecycle/profiler bounded metadata for generic telemetry. |
| Application-level network classification | EngineErrorClassifier sees wrapped network errors as statusCode 0 and does not flag them transient; use declarative transport retry for automatic network retries. |
| Webhook unknown-event ignore | parser returns null, but the stock Symfony controller does not provide a portable guaranteed 2xx acknowledgement for that result. |
| Observability helper wiring | ObservabilitySetup is effective only when registered on the dispatcher instance that receives the engine events. |
| Flex registration | after Composer install, verify config/bundles.php rather than assuming bundle registration. |
| Raw response caching occurs before mapping | cache stores the client response shape, then mapper executes on each hit. Mapper code changes affect future reads of an existing cached raw response. |


## 25.1 Production checklist

- Use a shared cache if dynamic token sharing must span workers/instances.
- Provide connectionId for accounts that can share a base URL.
- Disable or replace response caching for tenant-specific/body-dependent operations until the key captures the needed dimensions.
- Use allowed_hosts and block_private_networks when target URLs are dynamic or tenant-controlled.
- Keep retry_non_idempotent false unless the external operation is safe to repeat.
- Listen to scalar lifecycle events for metrics rather than serializing request/response objects.
- Redact application logs separately from profiler protections.
- Persist webhook idempotency in the same durable transaction boundary as the business side effect when duplicate delivery matters.
- Run debug:integration in deployment diagnostics to verify config without contacting providers.

# 26. End-to-end implementation recipes


## 26.1 REST integration with static API key

```yaml
# config/packages/integration_engine.yaml
integration_engine:
  integrations:
    acme:
      base_url: 'https://api.acme.test'
      config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/Acme/Acme.yaml'
      client: rest
      headers:
        Accept-Language: en
```

```text
# Acme.yaml
GetEmployee:
  action: App\Infrastructure\Integrations\Acme\GetEmployee\Request\GetEmployeeAction
  method: GET
  path: /employees/{id}
  authorization:
    type: api_key
    header: X-Api-Key
    token: '%env(ACME_API_KEY)%'
```

Use DefaultActionContext for id. The response mapper returns a typed DTO. The application facade returns that DTO or translates it through a gateway.


## 26.2 Dynamic bearer token

```text
FetchToken:
  action: App\...\FetchTokenAction
  method: POST
  path: /oauth/token
  body: App\...\FetchTokenBody

GetOrders:
  action: App\...\GetOrdersAction
  method: GET
  path: /orders
  authorization:
    type: dynamic
    action: FetchToken
    token_field: access_token
    ttl: 3500
```

The token action can itself have a mapper. The mapped ResponseInterface is converted to an array and access_token is read as a direct key. The business action receives static bearer auth after token resolution.


## 26.3 Multi-tenant shared endpoint

Use connection_resolver and always return a stable connectionId when several tenants share one base URL. This isolates dynamic-token cache entries. Do not enable response cache_ttl for tenant-specific responses unless you replace the response cache key strategy, because connectionId is not part of the built-in response-cache key.


## 26.4 Signed request

Use RequestMiddlewareInterface when the signature requires the final method, URL, headers and encoded body. Return a Request with added signature headers and call $next. Expect sendMany() to execute sequentially for built-in adapters while that middleware is configured.


## 26.5 Batch fan-out

```php
$requests = [];
foreach ($ids as $id) {
    $requests[$id] = new EngineRequest(
        actionName: GetEmployeeAction::getName(),
        context: DefaultActionContext::create(['id' => $id]),
    );
}

$results = $engine->sendMany($requests);
foreach ($results as $id => $result) {
    if ($result->isSuccess()) {
        $employee = $result->response();
    } else {
        $error = $result->error();
    }
}
```


## 26.6 Webhook mapping

Configure type_field, id_field, signature and event mapper in the integration YAML. The parser validates raw signature, JSON and event routing, then returns MappedRemoteEvent. Consume event() in an application handler or use the Symfony RemoteEvent path appropriate to the application.


# Appendix A. Configuration reference


## A.1 Bundle configuration example

```yaml
integration_engine:
  integrations:
    acme:
      config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/Acme/Acme.yaml'
      base_url: 'https://api.acme.test'
      client: rest
      timeout: 10
      max_duration: 30
      allowed_hosts: ['api.acme.test', '*.cdn.acme.test']
      block_private_networks: true
      retry:
        max_retries: 3
        delay_ms: 200
        multiplier: 2.0
        max_delay_ms: 2000
        jitter: 0.1
        status_codes: [423, 425, 429, 500, 502, 503, 504, 507, 510]
        retry_non_idempotent: false
      cache_service: integration_engine.cache.default
      connection_resolver: App\Infrastructure\Integrations\Acme\ConnectionResolver
      middlewares:
        - App\Infrastructure\Http\AuditMiddleware
      request_middlewares:
        - App\Infrastructure\Http\SigningMiddleware
      headers:
        Accept-Language: en
```


## A.2 Action YAML example

```text
FetchToken:
  action: App\...\FetchTokenAction
  method: POST
  path: /oauth/token
  body: App\...\FetchTokenBody

GetThing:
  action: App\...\GetThingAction
  method: GET
  path: /things/{id}
  authorization:
    type: dynamic
    action: FetchToken
    token_field: access_token
    ttl: 3600
  cache_ttl: 60
  timeout: 5
```


## A.3 Webhook YAML example

```yaml
webhooks:
  type_field: type
  id_field: data.id
  unknown_events: reject
  signature:
    type: hmac_sha256
    header: X-Signature
    secret: '%env(WEBHOOK_SECRET)%'
    prefix: 'sha256='
  events:
    order.created:
      mapper: App\Webhooks\OrderCreatedMapper
```


# Appendix B. Public contracts reference

| Contract | Essential API |
| --- | --- |
| ActionBodyInterface | create(array): self; toArray(): array |
| ActionContextInterface | create(array): self; toArray(): array |
| PathResolvableContextInterface | resolvePath(string): ?string |
| FormEncodedBodyInterface | Marker extending ActionBodyInterface |
| GraphQLBodyInterface | getQuery(): string; getVariables(): array |
| RequestHeadersInterface | toArray(): array<string,string> |
| ResponseInterface | toArray(): array<string,mixed> |
| ClientInterface | send(action, context?, headers?): raw response array |
| BatchClientInterface | sendMany(array<key,PreparedRequest>): per-key raw response or Throwable |
| DynamicBaseUrlClientInterface | withBaseUrl(string): static |
| ClientAdapterInterface | ClientInterface + getClientType(), requiresPath(), requiresMethod() |
| RequestMiddlewareInterface | handle(Request, callable): raw response array |
| BaseUrlAwareMiddlewareInterface | withBaseUrl(string): static |
| ConnectionResolverInterface | resolve(mixed): ConnectionCredentials |
| CachePort | get(key), set(key,value,ttl), delete(key) |
| ConfigPort | Action/webhook configuration abstraction |
| SignatureVerifierInterface | verify(rawBody, headers, SignatureConfig): void |
| WebhookEventInterface | Marker for typed inbound event DTOs |
| ErrorClassifierInterface | classify(Throwable): ErrorClassification |
| ResiliencePolicyInterface | shouldRetry, getBackoffMs, getMaxAttempts, getFallback, getName |


## B.1 Main concrete public values

| Value | Purpose |
| --- | --- |
| DefaultActionContext | Generic array-backed action context. |
| Request | Fully built outgoing request for request middleware. |
| ConnectionCredentials | Runtime base URL/auth/connectionId override. |
| EngineRequest | One send() equivalent packaged for batch. |
| BatchResult | Success or stored Throwable. |
| BatchResultCollection | Read-only keyed batch results + consolidation. |
| EmptyResponse | ResponseInterface used for no-response actions. |
| WebhookDefinition | Parsed generic webhook definition. |
| SignatureConfig | Signature scheme/header/secret/tolerance/prefix. |
| MappedRemoteEvent | Symfony RemoteEvent plus typed WebhookEventInterface. |


# Appendix C. Events and exceptions reference


## C.1 Lifecycle events

| Event | Fields |
| --- | --- |
| RequestSent | integrationName, action, method, path, timestamp, connectionId?, requestKey? |
| ResponseMapped | integrationName, action, durationMs, statusCode, responseClass, timestamp, requestKey? |
| RequestFailed | integrationName, action, durationMs, statusCode, exceptionClass, safe message, timestamp, requestKey? |
| TokenRefreshed | integrationName, action, reason, timestamp, requestKey? |
| WebhookReceived | integrationName, eventType, eventId, timestamp |
| WebhookRejected | integrationName, reason, timestamp |


## C.2 Webhook rejection reasons

- header_missing
- header_malformed
- signature_invalid
- timestamp_out_of_tolerance
- payload_invalid
- unknown_event

## C.3 Exception handling rule of thumb

Use normal try/catch around send(). For sendMany(), inspect BatchResult values rather than expecting per-item failures to be thrown. sendManyOrFail() is the convenience path when the caller wants every request dispatched but the first failed result rethrown afterward.


# Appendix D. Security checklist

- Configure allowed_hosts for integrations with runtime/dynamic targets.
- Enable block_private_networks when SSRF exposure is relevant.
- Do not use secret material as connectionId.
- Use shared token cache where multi-process token fetch duplication matters.
- Do not log RequestResponseException messages without redaction.
- Do not treat the profiler as a place to inspect provider bodies; it intentionally omits them.
- Keep retry_non_idempotent disabled unless request replay is demonstrably safe.
- Verify webhook signatures before JSON parsing/business logic.
- Use a durable application transaction for webhook deduplication/side effects.
- For custom clients, reproduce required host/network protections because bundle transport wrappers do not own that transport.
- Review response caching carefully for bodies and tenants sharing a base URL.

# Appendix E. Glossary

| Term | Meaning |
| --- | --- |
| Action | Immutable configured operation value created from a concrete AbstractAction class plus YAML. |
| Body | ActionBodyInterface payload object; may also supply path placeholders. |
| Context | Per-call values primarily used for path/query resolution. |
| Facade | Application-owned typed wrapper around IntegrationRegistry/IntegrationEngine. |
| Integration YAML | Per-integration file defining actions and optional webhooks. |
| Bundle configuration | Symfony configuration wiring clients, transport, cache, middleware and resolver. |
| Client middleware | Action-level middleware before request construction. |
| Request middleware | Middleware over the final Request immediately before transport. |
| PreparedRequest | Batch item after config, connection and authorization resolution. |
| Connection discriminator | Stable value used to namespace dynamic-token cache entries. |
| Mapper | Action-specific body/header -> typed response transformation. |
| Batch mapper | Second-stage consolidation of already mapped homogeneous batch results. |
| MappedRemoteEvent | Authenticated remote webhook carrying both raw payload and typed event DTO. |
| Lifecycle event | Scalar observability event emitted by the engine/webhook parser. |
| Transport retry | Symfony RetryableHttpClient policy configured per integration. |
| Resilience policy | Application-facing decision abstraction; not automatically executed by IntegrationEngine. |


# Final mental model

A developer who understands the following sequence understands the bundle: configuration creates a named engine; the engine resolves an immutable action; runtime connection data may replace URL/auth; dynamic auth may fetch/cache a token; client middleware runs; the adapter constructs a final request and optionally runs request middleware; transport returns a decoded array plus headers/status; a mapper produces a typed response; lifecycle metadata is emitted; batches apply the same rules per key with isolation and optional concurrency; webhooks mirror the same philosophy inbound by verifying raw bytes before mapping into typed events.

Everything else in IntegrationEngine exists to keep that sequence predictable, observable, testable and replaceable at explicit boundaries.