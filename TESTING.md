# IntegrationEngine — Testing Guide

## Philosophy

The goal is not coverage. The goal is:

> No integration can break the engine contract without being detected immediately.

Tests validate behaviour, not implementation. Fakes replace real infrastructure
at the ports. No mocks, no spies on internal methods, no coupling to private
state. Each test represents a real scenario that a developer or the engine
itself can encounter in production.

---

## Structure

```
tests/
├── Core/
│   ├── IntegrationEngineTestCase.php   ← shared base for engine tests
│   ├── AbstractActionTest.php
│   ├── AbstractMapperTest.php
│   ├── AuthorizationConfigTest.php
│   ├── BatchResultTest.php
│   ├── BatchSendTest.php
│   ├── DefaultActionContextTest.php
│   ├── DynamicAuthTest.php
│   ├── EngineContractTest.php
│   ├── ExceptionMessagesTest.php
│   └── IntegrationRegistryTest.php
├── Fake/
│   ├── FakeBatchClient.php
│   ├── FakeCache.php
│   ├── FakeClient.php
│   ├── FakeConfigPort.php
│   ├── FakeContext.php
│   ├── FakePathAction.php
│   ├── FakeProtectedAction.php
│   ├── FakeTokenAction.php
│   ├── FakeTokenMapper.php
│   └── FakeTokenResponse.php
├── Infrastructure/
│   ├── ClientAdapterResolverTest.php
│   ├── GraphQLClientAdapterHeadersTest.php
│   ├── GraphQLClientAdapterTest.php
│   ├── Psr6CacheAdapterTest.php
│   ├── SymfonyHttpClientAdapterBatchTest.php
│   ├── SymfonyHttpClientAdapterBodyTest.php
│   ├── SymfonyHttpClientAdapterHeadersTest.php
│   ├── SymfonyHttpClientAdapterResolveHeadersTest.php
│   └── YamlConfigAdapterTest.php
└── Bundle/
    ├── Command/
    │   └── MakeIntegrationCommandTest.php
    ├── DependencyInjection/
    │   ├── ConfigurationTest.php
    │   ├── IntegrationCompilerPassTest.php
    │   └── IntegrationEngineExtensionTest.php
    └── Generator/
        ├── IntegrationContextTest.php
        ├── IntegrationFileGeneratorTest.php
        └── TemplateRendererTest.php
```

Test counts rot quickly in prose — run `./vendor/bin/phpunit --testdox` for the live list.

---

## Test suites

### `AbstractActionTest`

Covers the `AbstractAction` contract in full isolation. No engine, no HTTP,
no mapper. Pure unit tests on the action's path resolution logic and
authorization wiring.

| Test | What it protects |
|------|-----------------|
| `getMethodReturnsConstructedMethod` | Method is stored and returned correctly |
| `getPathReturnsStaticPathWithoutContext` | Static paths work without context |
| `getPathReturnsStaticPathWhenContextIsNull` | Explicit null context is safe |
| `getPathResolvesPlaceholderFromContext` | Single `{param}` is resolved from context |
| `getPathResolvesMultiplePlaceholders` | Multiple `{param}` in one path all resolve |
| `getPathThrowsForMissingPlaceholder` | Missing context key throws `PathResolutionException` at resolution time, not HTTP time |
| `getPathThrowsForNonScalarPlaceholderValue` | Non-scalar context value throws immediately |
| `actionDoesNotStoreContext` | **Regression** — action called twice with different contexts resolves each correctly. Protects against the bug where a cached action carries the context of a previous request |
| `sameActionInstanceCanBeCalledWithDifferentContexts` | Same instance is safe to reuse across calls |
| `getAuthorizationReturnsNullByDefault` | No auth config means null, not an error |
| `getAuthorizationReturnsInjectedConfig` | Injected auth config is returned as-is |
| `contextCanOverridePathResolution` | A `PathResolvableContextInterface` context takes full control of the path |
| `contextResolutionReceivesRawPathFromYaml` | The custom resolver receives the raw path, not a pre-resolved one |
| `contextReturningNullFallsBackToDefaultResolver` | Returning `null` delegates to the `{placeholder}` resolver |
| `contextReturningEmptyStringThrows` | An empty resolved path throws `PathResolutionException` instead of producing a broken URL |

---

### `DynamicAuthTest`

Covers the full dynamic authorization flow via the engine. Uses
`IntegrationEngineTestCase` as base. All fakes are in-memory — no HTTP,
no real cache.

| Test | What it protects |
|------|-----------------|
| `dynamicAuthResolvesTokenAndSetsStaticAuth` | Token is fetched, extracted, and substituted as `bearer` auth before the real request |
| `dynamicAuthUsesApiKeyForCustomHeader` | When `header` is customized, auth type becomes `api_key` with the correct header name |
| `dynamicAuthCachesTokenOnFirstCall` | Auth action is only called once; token is cached for subsequent calls |
| `dynamicAuthThrowsWhenTokenFieldMissing` | If the auth response does not contain `token_field`, `DynamicAuthException` is thrown before the real request executes |
| `dynamicAuthUsesTokenFromCacheWhenAvailable` | Pre-cached token is used directly without calling the auth action |
| `contextReachesClientAfterDynamicAuthReconstruction` | **Regression** — when dynamic auth reconstructs the action, the original context still reaches the client. Protects against the bug where context was lost after token substitution |
| `dynamicAuthUsesCustomPrefixInAuthorizationHeader` | When `prefix` is set, the resolved `StaticAuthorizationConfig` carries the custom prefix — protects APIs using `Authorization: Token …` or other non-Bearer schemes |
| `dynamicAuthKeepsCustomPrefixOnCustomHeader` | **Regression** — a custom header plus a custom prefix keeps the prefix; it used to be silently dropped |
| `dynamicAuthDefaultsToBearerPrefixOnAuthorizationHeader` | No explicit prefix on the `Authorization` header defaults to `Bearer` |
| `dynamicAuthCastsIntegerTokenToString` | A numeric token field is cast to string instead of failing |
| `rejectedCachedTokenIsDroppedAndRequestRetriedWithFreshToken` | A cached token rejected with 401 is evicted; the request retries once with a fresh token |
| `freshTokenRejectedWith401IsNotRetried` | A freshly fetched token that gets 401 propagates — refetching would yield the same token |
| `non401ErrorWithCachedTokenIsNotRetried` | A 500 does not evict the token nor trigger a retry |
| `second401AfterRetryPropagates` | Exactly one retry: a second 401 propagates to the caller |
| `actionRemainsStatelessAcrossMultipleSendCalls` | Same action resolves different paths across successive calls with different contexts |

---

### `EngineContractTest`

Covers the `IntegrationEngine::send()` contract end-to-end. Uses
`IntegrationEngineTestCase` as base. Verifies the full pipeline:
Config → Client → Mapper → Response, and all named exceptions.

| Test | What it protects |
|------|-----------------|
| `engineExecutesFullFlowAndReturnsMappedResponse` | Full pipeline: config loads action, client executes, mapper transforms, typed response returned |
| `mapperReceivesRawResponseAndBuildsTypedObject` | Mapper receives the exact raw array from the client and builds a typed object |
| `actionWithNoResponseReturnsEmptyResponse` | `hasResponse: false` returns `EmptyResponse` regardless of what the client returns |
| `actionWithNoResponseStillCallsClient` | The engine calls the client even for no-response actions — the HTTP request still happens |
| `unknownActionNameThrowsActionNotFoundException` | Calling `send()` with an unregistered action name throws immediately |
| `actionWithResponseButNoMapperThrows` | `hasResponse: true` with `mapper: null` throws `NotMappedActionException` |
| `mapperPointingToWrongActionThrows` | Mapper whose `getAction()` does not match the executing action throws `MapperActionMismatchException` |
| `contextIsPassedThroughToClient` | Context provided to `send()` reaches the HTTP client |

---

### `SymfonyHttpClientAdapterHeadersTest`

Covers the three-layer header precedence system in `SymfonyHttpClientAdapter`.
Uses a `SpyHttpClient` inline — a test double that records the options passed
to `HttpClientInterface::request()`.

| Test | What it protects |
|------|-----------------|
| `defaultHeadersFromYamlAreSent` | YAML default headers reach the HTTP request |
| `authHeaderOverridesDefaultHeader` | Auth-resolved header overrides the same key from YAML defaults |
| `callerHeaderOverridesAuthHeader` | Per-request caller header overrides the same key from auth |
| `noDefaultHeadersSendsOnlyAccept` | Without defaults, only `Accept: application/json` is sent |
| `allThreeLayersMergeInCorrectOrder` | All three layers coexist: YAML sets a key, auth overrides it, caller overrides that. Other YAML keys survive |
| `contextIsPassedToGetPath` | Context provided to `send()` is used to resolve path placeholders in the URL |

---

### `ClientAdapterResolverTest`

Covers `ClientAdapterResolver` in isolation. Verifies registration, resolution,
override precedence, and error messaging.

| Test | What it protects |
|------|-----------------|
| `resolveReturnsBultInRestAdapter` | `rest` resolves to `SymfonyHttpClientAdapter` after registration |
| `resolveReturnsBultInGraphQLAdapter` | `graphql` resolves to `GraphQLClientAdapter` after registration |
| `resolveUnknownTypeThrowsInvalidArgumentException` | Unknown type throws with the type name and registered types in the message |
| `laterRegistrationOverridesEarlier` | Re-registering a type replaces the previous adapter — project adapters win |
| `allReturnsFullMap` | `all()` returns the complete registered map |
| `resolveOnEmptyResolverThrowsWithNoneMessage` | Empty resolver error message says "none" not a blank string |
| `customAdapterIsRegisteredAndResolved` | Custom adapter type is registered and resolved correctly |

---

### `GraphQLClientAdapterTest`

Covers `GraphQLClientAdapter` in full isolation. Uses a `GQLSpyHttpClient`
inline — records method, URL, and options. Tests body serialisation, data
extraction, error handling, auth headers, and adapter capabilities.

| Test | What it protects |
|------|-----------------|
| `graphQLBodyIsSerialisedAsQueryAndVariables` | Body is sent as `{ query, variables }` JSON to the endpoint |
| `alwaysPostsToEndpointUrlIgnoringActionPath` | Adapter always uses the configured endpoint URL regardless of action path |
| `graphQLDataIsExtractedBeforeMapper` | Mapper receives only `data[]`, not the full GraphQL envelope |
| `nullDataKeyReturnsEmptyArray` | `data: null` in the response returns `[]` without error |
| `graphQLErrorsInResponseThrowsRequestResponseException` | `errors[]` in the response body (HTTP 200) throws with the error message |
| `graphQLErrorWithoutMessageThrowsGenericError` | `errors[]` without a `message` key throws a generic GraphQL error |
| `http4xxThrowsRequestResponseException` | HTTP 4xx from the endpoint throws `RequestResponseException` |
| `nonGraphQLBodyThrowsImmediately` | Body that does not implement `GraphQLBodyInterface` throws before any HTTP call |
| `nullBodyThrowsImmediately` | Null body throws before any HTTP call |
| `defaultHeadersAreSentWithContentType` | Default headers and `Content-Type: application/json` are merged correctly |
| `bearerAuthHeaderIsApplied` | Bearer auth config produces `Authorization: Bearer {token}` |
| `callerHeaderOverridesAuthHeader` | Per-request caller header overrides the auth-resolved header |

---

### `Psr6CacheAdapterTest`

Covers `Psr6CacheAdapter`, the PSR-6 bridge over `CachePort`. Uses a
`SpyCachePool` inline — records keys and TTLs passed to the pool, and supports
pre-seeding values for hit scenarios.

| Test | What it protects |
|------|-----------------|
| `getReturnsCachedValueOnHit` | A seeded key is returned correctly |
| `getReturnsNullOnMiss` | A missing key returns null, not an exception |
| `setStoresValueAndTtl` | Value and TTL are forwarded to the pool correctly |
| `deleteRemovesStoredValue` | `delete()` evicts the entry — the engine relies on this for 401 token invalidation |
| `deleteSanitizesKeyLikeGetAndSet` | `set()` and `delete()` sanitize identically, so the delete targets the stored item |
| `reservedPsr6CharactersAreSanitized` | PSR-6 reserved chars (`{}()/\@:`) are replaced before the key reaches the pool |
| `dotsAreSanitized` | Dots are sanitized — protects keys like `integration_engine.token.*` which some pools reject |

---

### Other Core / Infrastructure suites

| Suite | What it covers |
|------|-----------------|
| `ExceptionMessagesTest` | Every exception message exposes the values a developer needs to debug (action names, field names, status codes) |
| `SymfonyHttpClientAdapterBodyTest` | Body serialisation rules per HTTP method and error mapping (4xx/5xx → `RequestResponseException`, network errors, 204/empty responses) |
| `SymfonyHttpClientAdapterResolveHeadersTest` | The `ResolvesAuthHeaders` trait: `bearer`, `basic`, `api_key` (with and without `prefix`), unknown types, and header precedence |
| `GraphQLClientAdapterHeadersTest` | Header precedence layers for the GraphQL adapter, including auth resolution |
| `BatchSendTest` | `sendMany()`/`sendManyOrFail()`: key preservation, mixed actions, partial results, batch dynamic auth (token once per batch, shared 401 retry, fresh-token 401s final), batch-client routing and misbehaving batch clients |
| `BatchResultTest` | The success/failure envelope: accessors and `response()` rethrowing the stored failure |
| `SymfonyHttpClientAdapterBatchTest` | Concurrent dispatch (all requests sent before any response is consumed), per-key error envelopes, per-request path resolution |

---

## Bundle suites

Cover the Symfony integration layer in `tests/Bundle/` — wiring is verified
against a real `ContainerBuilder`, the command against `CommandTester` with
a temporary project directory. No kernel boot needed.

| Suite | What it covers |
|------|-----------------|
| `ConfigurationTest` | Config tree defaults and validation: `base_url`/`client_service` requirement, empty client rejection, headers preserved verbatim (**regression** — dashes in header names used to be mangled to underscores) |
| `IntegrationEngineExtensionTest` | `load()` exposes processed integrations as a parameter, registers built-in adapters with the client-adapter tag, and the bundle adds the compiler pass |
| `IntegrationCompilerPassTest` | Per-integration service wiring: config adapter, HTTP client, engine, registry registration; custom `client_service`/`cache_service`; invalid tagged services skipped; configuration errors throw at compile time |
| `IntegrationContextTest` | Namespace building and the `hasBody`/`hasResponse` rules per HTTP method and adapter type |
| `TemplateRendererTest` | Generated PHP templates (integration, action, mapper, response) and YAML entries are correct and syntactically valid |
| `IntegrationFileGeneratorTest` | File layout per action, response layer omitted for DELETE, YAML append without erasing previous actions |
| `MakeIntegrationCommandTest` | The interactive `make:integration` flow end-to-end: first run, adding actions, DELETE actions, GraphQL integrations, skip-without-force, unknown client type |

---

## Fakes

Fakes are reusable in-memory implementations of the engine's ports. They live
in `tests/Fake/` and are shared across test suites. No mocks, no Mockery,
no PHPUnit `createMock()`.

| Fake | Implements | Purpose |
|------|-----------|---------|
| `FakeCache` | `CachePort` | In-memory key-value store with TTL ignored. Supports `delete()`. Used to pre-seed tokens and verify cache hits and evictions |
| `FakeClient` | `ClientInterface` | Records the last action, last context, and call count per action name. Returns pre-configured responses; `queueException()` throws on the next call to simulate HTTP failures (e.g. 401 retry scenarios) |
| `FakeBatchClient` | `BatchClientInterface`, `ClientInterface` | Wraps a `FakeClient` and records every `sendMany()` call, so tests can assert the engine routed a batch through the batch interface instead of looping over `send()` |
| `FakeConfigPort` | `ConfigPort` | Registry of actions registered by name. Throws `ActionNotFoundException` for unknown names |
| `FakeContext` | `ActionContextInterface` | General-purpose context with arbitrary key-value data |
| `FakeTokenAction` | `AbstractAction` | Action that represents a token-fetching endpoint. Has a response and a mapper |
| `FakeTokenMapper` | `AbstractMapper` | Passes the raw response through as `FakeTokenResponse` |
| `FakeTokenResponse` | `ResponseInterface` | Wraps the raw array from the token endpoint |
| `FakePathAction` | `AbstractAction` | Action with a path parameter (`/orders/{id}`). No response needed — used to verify context propagation |
| `FakeProtectedAction` | `AbstractAction` | Action that requires authorization. No response needed — used to verify auth substitution |

---

## Base class

`IntegrationEngineTestCase` is an abstract `TestCase` that wires up the
full engine with fakes in `setUp()`. `DynamicAuthTest` and
`EngineContractTest` extend it. `AbstractActionTest` and
`SymfonyHttpClientAdapterHeadersTest` do not — they test components in
isolation and do not need the full engine.

---

## Running the tests

```bash
./vendor/bin/phpunit
```

With coverage (requires Xdebug or PCOV):

```bash
./vendor/bin/phpunit --coverage-text
```

---

## Mutation testing — don't trust 100% MSI

A 100% MSI from Infection can contain false positives and **always requires a
second, manual review**:

- A mutant can be "killed" by a side effect — e.g. a `TypeError` from a
  constructor — instead of by an assertion that validates the real behaviour.
  The score counts it as killed either way.
- The MSI only measures **covered** code. A class with zero tests contributes
  nothing to the score, so the aggregate number can stay at 100% while entire
  classes go untested. This happened in this project: `YamlConfigAdapter`,
  `IntegrationRegistry` and `DefaultActionContext` sat at 0% coverage behind a
  100% MSI.

When adding or changing tests, do not accept the run just because `make ci`
reports 100%. Double-check:

1. Each test kills mutants through behavioural assertions (returned values,
   exception messages) — not accidentally.
2. Per-class coverage, not just the aggregate MSI.
3. `infection.log` for escaped mutants and for mutants killed by accident.