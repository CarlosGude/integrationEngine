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
│   ├── DynamicAuthTest.php
│   └── EngineContractTest.php
├── Fake/
│   ├── FakeCache.php
│   ├── FakeClient.php
│   ├── FakeConfigPort.php
│   ├── FakeContext.php
│   ├── FakePathAction.php
│   ├── FakeProtectedAction.php
│   ├── FakeTokenAction.php
│   ├── FakeTokenMapper.php
│   └── FakeTokenResponse.php
└── Infrastructure/
    └── SymfonyHttpClientAdapterHeadersTest.php
```

---

## Test suites

### `AbstractActionTest` — 14 tests

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
| `customPathResolverIsUsedWhenProvided` | `resolvePathCallback()` override takes full control |
| `customPathResolverReceivesContext` | Custom resolver receives context correctly |
| `customPathResolverThrowsIfNotReturningString` | Bad resolver (returns non-string) throws clearly |

---

### `DynamicAuthTest` — 7 tests

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
| `actionRemainsStatelessAcrossMultipleSendCalls` | Same action resolves different paths across successive calls with different contexts |

---

### `EngineContractTest` — 8 tests

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

### `SymfonyHttpClientAdapterHeadersTest` — 6 tests

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

## Fakes

Fakes are reusable in-memory implementations of the engine's ports. They live
in `tests/Fake/` and are shared across test suites. No mocks, no Mockery,
no PHPUnit `createMock()`.

| Fake | Implements | Purpose |
|------|-----------|---------|
| `FakeCache` | `CachePort` | In-memory key-value store with TTL ignored. Used to pre-seed tokens and verify cache hits |
| `FakeClient` | `ClientInterface` | Records the last action, last context, and call count per action name. Returns pre-configured responses |
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
