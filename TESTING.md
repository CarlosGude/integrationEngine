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
│   ├── AbstractBatchMapperTest.php
│   ├── AbstractMapperTest.php
│   ├── AuthorizationConfigTest.php
│   ├── BatchResultCollectionTest.php
│   ├── BatchResultTest.php
│   ├── BatchSendSadPathTest.php
│   ├── BatchSendTest.php
│   ├── DefaultActionContextTest.php
│   ├── DynamicAuthSadPathTest.php
│   ├── DynamicAuthTest.php
│   ├── EngineContractTest.php
│   ├── ExceptionMessagesTest.php
│   └── IntegrationRegistryTest.php
├── Fake/
│   ├── FakeBatchClient.php
│   ├── FakeBatchMapper.php
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
│   ├── GraphQLClientAdapterBodyTest.php
│   ├── GraphQLClientAdapterErrorTest.php
│   ├── GraphQLClientAdapterHeadersTest.php
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

### `DynamicAuthTest` — happy path

Covers the dynamic authorization flow via the engine. Uses
`IntegrationEngineTestCase` as base. All fakes are in-memory — no HTTP,
no real cache.

| Test | What it protects |
|------|-----------------|
| `dynamicAuthResolvesTokenAndSetsStaticAuth` | Token is fetched, extracted, and substituted as `bearer` auth before the real request |
| `dynamicAuthCastsIntegerTokenToString` | A numeric token field is cast to string instead of failing |
| `dynamicAuthUsesCustomPrefixInAuthorizationHeader` | When `prefix` is set, the resolved `StaticAuthorizationConfig` carries the custom prefix — protects APIs using `Authorization: Token …` or other non-Bearer schemes |
| `dynamicAuthUsesApiKeyForCustomHeader` | When `header` is customized, auth type becomes `api_key` with the correct header name |
| `dynamicAuthKeepsCustomPrefixOnCustomHeader` | **Regression** — a custom header plus a custom prefix keeps the prefix; it used to be silently dropped |
| `dynamicAuthDefaultsToBearerPrefixOnAuthorizationHeader` | No explicit prefix on the `Authorization` header defaults to `Bearer` |
| `dynamicAuthCachesTokenOnFirstCall` | Auth action is only called once; token is cached for subsequent calls |
| `dynamicAuthUsesTokenFromCacheWhenAvailable` | Pre-cached token is used directly without calling the auth action |
| `contextReachesClientAfterDynamicAuthReconstruction` | **Regression** — when dynamic auth reconstructs the action, the original context still reaches the client. Protects against the bug where context was lost after token substitution |
| `actionRemainsStatelessAcrossMultipleSendCalls` | Same action resolves different paths across successive calls with different contexts |

---

### `DynamicAuthSadPathTest`

Covers dynamic auth failure scenarios. Uses `IntegrationEngineTestCase` as base.

| Test | What it protects |
|------|-----------------|
| `dynamicAuthThrowsWhenTokenFieldMissing` | If the auth response does not contain `token_field`, `DynamicAuthException` is thrown before the real request executes |
| `nonScalarTokenFieldThrows` | A non-scalar token field value (e.g. an array) throws `DynamicAuthException` |
| `rejectedCachedTokenIsDroppedAndRequestRetriedWithFreshToken` | A cached token rejected with 401 is evicted; the request retries once with a fresh token |
| `freshTokenRejectedWith401IsNotRetried` | A freshly fetched token that gets 401 propagates — refetching would yield the same token |
| `non401ErrorWithCachedTokenIsNotRetried` | A 500 does not evict the token nor trigger a retry |
| `second401AfterRetryPropagates` | Exactly one retry: a second 401 propagates to the caller |

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

### `BatchSendTest` — happy path

Covers `sendMany()` and `sendManyOrFail()` for the normal-operation paths.
Uses `IntegrationEngineTestCase` as base.

| Test | What it protects |
|------|-----------------|
| `sendManyPreservesKeysAndMapsEachActionWithItsOwnMapper` | Keys are preserved and each item is mapped by its own action's mapper |
| `sendManyStoresActionClassPerItemInCollection` | `BatchResultCollection::actionClassFor()` returns the correct class per key |
| `sendManyWithEmptyArrayReturnsEmptyCollectionWithoutTouchingClient` | Empty input returns an empty `BatchResultCollection` without any client call |
| `sendManyWithEmptyArrayDoesNotInvokeABatchClient` | Empty input does not call `BatchClientInterface::sendMany()` |
| `sendManyOrFailReturnsMappedResponsesPreservingKeys` | `sendManyOrFail()` unwraps to `array<key, ResponseInterface>` when all succeed |
| `sendManyRoutesThroughBatchClientWhenAvailable` | When the client implements `BatchClientInterface`, the engine routes through it |

---

### `BatchSendSadPathTest`

Covers failure scenarios in `sendMany()`: partial failures, unknown actions,
mapper mismatches, dynamic auth edge cases, and misbehaving batch clients.
Uses `IntegrationEngineTestCase` as base.

| Test | What it protects |
|------|-----------------|
| `sendManyReturnsPartialResultsWhenOneRequestFails` | A failure on one key does not abort other keys |
| `sendManyCapturesUnknownActionAsFailureWithoutAbortingBatch` | An unknown action name becomes a failure result, not an exception |
| `sendManyCapturesMapperMismatchAsFailureWithoutAbortingBatch` | A mapper invariant violation becomes a failure result, not an exception |
| `sendManyOrFailThrowsTheFirstFailureInRequestOrder` | `sendManyOrFail()` throws the first failure in input order (not arbitrary) |
| `sendManyResolvesDynamicTokenOncePerBatch` | Only one token fetch per token action per batch, regardless of how many items share it |
| `sendManyRetriesAllCachedToken401sWithOneFreshToken` | All items holding a stale cached token get retried with a single freshly fetched token |
| `sendManyDoesNotRetry401WhenTokenWasFetchedInThisBatch` | A token first fetched during this batch is treated as fresh — 401s are final for all items holding it |
| `sendManyDoesNotRetryNon401FailuresEvenWithCachedToken` | A 500 does not evict the token nor trigger a retry |
| `sendManyFailsItemWhenTokenRefetchFailsDuringRetry` | If the token endpoint is down during the retry phase, the item fails with that error |
| `sendManyFailsKeysMissingFromABatchClientResponse` | A batch client that omits a key from its result produces a failure for that key |

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

### GraphQL adapter suites

The GraphQL adapter tests are split into three files by concern:

**`GraphQLClientAdapterBodyTest`** — body serialization, data extraction, adapter capabilities

| Test | What it protects |
|------|-----------------|
| `graphQLBodyIsSerialisedAsQueryAndVariables` | Body is sent as `{ query, variables }` JSON to the endpoint |
| `alwaysPostsToEndpointUrlIgnoringActionPath` | Adapter always uses the configured endpoint URL regardless of action path |
| `graphQLDataIsExtractedBeforeMapper` | Mapper receives only `data[]`, not the full GraphQL envelope |
| `nullDataKeyReturnsEmptyArray` | `data: null` in the response returns `[]` without error |
| `emptyErrorsArrayDoesNotThrow` | An empty `errors: []` does not throw |
| `graphQLAdapterDoesNotRequirePath` | `requiresPath()` returns false |
| `graphQLAdapterDoesNotRequireMethod` | `requiresMethod()` returns false |
| `graphQLAdapterClientTypeIsGraphql` | `getClientType()` returns `'graphql'` |

**`GraphQLClientAdapterErrorTest`** — all error paths

| Test | What it protects |
|------|-----------------|
| `graphQLErrorsInResponseThrowsWithStatusCode200` | `errors[]` in the response body (HTTP 200) throws `RequestResponseException` with the error message and status 200 |
| `graphQLErrorWithoutMessageThrowsGenericErrorWithStatusCode200` | `errors[]` without a `message` key throws a generic error with status 200 |
| `nonGraphQLBodyThrowsWithStatusCodeZero` | Body that does not implement `GraphQLBodyInterface` throws before any HTTP call, with status 0 |
| `nullBodyThrowsWithStatusCodeZero` | Null body throws before any HTTP call, with status 0 |
| `http4xxThrowsRequestResponseException` | HTTP 4xx from the endpoint throws `RequestResponseException` |
| `http400ExactlyThrowsRequestResponseException` | HTTP 400 exactly is treated as an error (boundary check) |
| `http4xxStatusCodeIsPreservedWhenGetContentThrows` | Status code is preserved even when `getContent()` throws during error reporting |
| `networkErrorDuringRequestIsWrappedWithStatusCodeZero` | A transport-level exception is wrapped with status 0 |

**`GraphQLClientAdapterHeadersTest`** — header resolution (bearer, basic, api_key, default, caller override)

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

### Other Core suites

| Suite | What it covers |
|------|-----------------|
| `ExceptionMessagesTest` | Every exception message exposes the values a developer needs to debug (action names, field names, status codes) |
| `BatchResultTest` | The success/failure envelope: `isSuccess()`, `response()` (rethrowing stored failure), `error()` |
| `BatchResultCollectionTest` | Collection API: key preservation, iteration, `ArrayAccess`, `hasFailures()`, `responses()`, `errors()`, `actionClassFor()`, `mapWith()` validation |
| `AbstractBatchMapperTest` | The batch mapper invariant: passes when all items share the declared action, throws `BatchMapperActionMismatchException` on mismatch, skips null-class items (prep failures), always calls `consolidate()` |

---

### Other Infrastructure suites

| Suite | What it covers |
|------|-----------------|
| `SymfonyHttpClientAdapterBodyTest` | Body serialisation rules per HTTP method and error mapping (4xx/5xx → `RequestResponseException`, network errors, 204/empty responses) |
| `SymfonyHttpClientAdapterResolveHeadersTest` | The `ResolvesAuthHeaders` trait: `bearer`, `basic`, `api_key` (with and without `prefix`), unknown types, and header precedence |
| `SymfonyHttpClientAdapterBatchTest` | Concurrent dispatch: all requests dispatched before any response is consumed; per-key error envelopes (HTTP error, network error, path resolution error); per-request path resolution with context |

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
| `FakeBatchMapper` | `AbstractBatchMapper` | Captures the `BatchResultCollection` passed to `consolidate()` via a public static property, so tests can assert what the mapper received. Returns a count-based `FakeTokenResponse` |
| `FakeConfigPort` | `ConfigPort` | Registry of actions registered by name. Throws `ActionNotFoundException` for unknown names |
| `FakeContext` | `ActionContextInterface` | General-purpose context with arbitrary key-value data |
| `FakeTokenAction` | `AbstractAction` | Action that represents a token-fetching endpoint. Has a response and a mapper |
| `FakeTokenMapper` | `AbstractMapper` | Passes the raw response through as `FakeTokenResponse` |
| `FakeTokenResponse` | `ResponseInterface` | Wraps the raw array from the token endpoint |
| `FakePathAction` | `AbstractAction` | Action with a path parameter (`/orders/{id}`). No response — used to verify context propagation and path resolution |
| `FakeProtectedAction` | `AbstractAction` | Action that requires authorization. No response — used to verify auth substitution |

---

## Base class

`IntegrationEngineTestCase` is an abstract `TestCase` that wires up the
full engine with fakes in `setUp()`: `$this->engine`, `$this->config`,
`$this->client`, `$this->cache`. Engine contract tests and all batch tests
extend it. Tests that verify components in isolation (`AbstractActionTest`,
adapter tests) do not — they construct what they need directly.

---

## Running the tests

```bash
./vendor/bin/phpunit
```

Single file or method:

```bash
./vendor/bin/phpunit tests/Core/BatchSendTest.php
./vendor/bin/phpunit --filter=sendManyPreservesKeys tests/Core/BatchSendTest.php
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
