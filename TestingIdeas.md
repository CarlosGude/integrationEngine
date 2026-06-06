# IntegrationEngine — Testing Strategy

The goal is not coverage. The goal is:

> No integration can break the engine contract without being detected immediately.

Tests validate behaviour, not implementation. Fakes replace real infrastructure
at the ports. No mocks, no spies, no coupling to internal details.

---

## What exists

### `AbstractActionTest`
Covers `AbstractAction` fully:
- `getMethod()` returns constructed method
- `getPath()` static path, with and without context
- `getPath()` resolves single and multiple placeholders
- `getPath()` throws `RuntimeException` for missing placeholder
- `getPath()` throws `RuntimeException` for non-scalar placeholder value
- Action does not store context between calls (statelessness regression)
- Same instance resolves different contexts across calls
- `getAuthorization()` returns null by default and injected config
- Custom `resolvePathCallback()` is used when provided
- Custom resolver receives context
- Custom resolver throws if it does not return a string

### `DynamicAuthTest`
Covers the full dynamic auth flow via the engine:
- Token resolved and substituted as `StaticAuthorizationConfig` (bearer)
- Custom header resolves as `api_key`
- Token cached on first call — auth action not called again
- Token missing from auth response throws `RuntimeException`
- Pre-cached token used without calling auth action
- Context reaches client after dynamic auth reconstructs the action (regression)
- Action remains stateless across multiple `send()` calls with different contexts

### `SymfonyHttpClientAdapterHeadersTest`
Covers header precedence in `SymfonyHttpClientAdapter`:
- YAML default headers are sent
- Auth header overrides YAML default
- Caller header overrides auth header
- No defaults sends only `Accept: application/json`
- All three layers merge in correct order
- Context is passed to `getPath()` and resolves the URL

### `EngineContractTest`
Covers the core engine contract:
- Full flow: Config → Client → Mapper → Response
- Mapper receives raw response and builds typed object
- `hasResponse: false` returns `EmptyResponse`
- `hasResponse: false` still calls the client
- Unknown action name throws `ActionNotFoundException`
- `hasResponse: true` with `mapper: null` throws `NotMappedActionException`
- Mapper `getAction()` mismatch throws `MapperActionMismatchException`
- Context is passed through to the client

---

## What is not tested yet

### `YamlConfigAdapter`
The declarative config system — the primary developer-facing surface — has no
tests. Priority:
- Valid YAML with GET action resolves correctly
- Path with `{param}` placeholder is preserved as-is (resolved at call time)
- Action class not found throws `ActionNotFoundException`
- Malformed YAML (missing `method`, missing `path`, missing `action`) throws
  with a descriptive message
- Auth block (static and dynamic) is parsed into the correct config objects

### `IntegrationRegistry`
- `get()` with a registered name returns the engine
- `get()` with an unknown name throws `IntegrationNotFoundException`

### `DefaultActionContext`
- `create()` builds context from array
- `toArray()` returns the original data

---

## Known exceptions and where they are thrown

| Exception | Thrown in |
|---|---|
| `ActionNotFoundException` | `YamlConfigAdapter::getAction()` |
| `IntegrationNotFoundException` | `IntegrationRegistry::get()` |
| `MapperActionMismatchException` | `IntegrationEngine::applyMapper()`, `AbstractMapper::map()` |
| `NotMappedActionException` | `IntegrationEngine::applyMapper()` |
| `RequestResponseException` | `SymfonyHttpClientAdapter::send()` |
| `RuntimeException` | `AbstractAction::getPath()` (missing/non-scalar param, bad resolver) |
| `RuntimeException` | `IntegrationEngine::resolveToken()` (missing token field, non-scalar token) |

---

## Fakes available for reuse

All fakes live inline in their test files. Extract to a shared `tests/Fake/`
directory when the test count justifies it.

| Fake | File | What it does |
|---|---|---|
| `DynFakeCache` | `DynamicAuthTest` | In-memory CachePort |
| `DynFakeClient` | `DynamicAuthTest` | Records last action, last context, call count per action |
| `DynFakeConfigPort` | `DynamicAuthTest` | Registers actions by name |
| `EngFakeCache` | `EngineContractTest` | In-memory CachePort |
| `EngFakeClient` | `EngineContractTest` | Records last context, call count per action |
| `EngFakeConfigPort` | `EngineContractTest` | Registers actions by name |
| `SpyHttpClient` | `SymfonyHttpClientAdapterHeadersTest` | Records last options and URL |

---

## Philosophy

The engine has one job: given a name, produce a typed response. Every test
validates a variation of that contract. If a test cannot be expressed as
"given this input to `send()`, the output or exception matches expectation",
it is testing implementation, not behaviour.