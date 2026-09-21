# Changelog

All notable changes to this project are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [7.0.2] - 2026-09-21

### Fixed

- **`CsvParser` dropped any row whose whole line was `0`.** A duplicated empty-line
  guard used `empty($line)`, and `empty('0')` is `true` in PHP, so a single-column
  row carrying the value `0` was silently skipped. The guard above it already
  handled genuinely empty lines, so the duplicate is gone. Parsing a
  single-column CSV of prices or quantities no longer loses rows.
- **`CsvParser` now passes `str_getcsv()`'s `$escape` explicitly as `''`.** PHP's
  default is `"\\"`, which emits a deprecation from 8.4 and flips to `''` in
  PHP 9; pinning it keeps one behaviour across versions. This matches RFC 4180,
  which has no escape character and escapes an enclosure by doubling it.
  **Output changes** for one input shape: a backslash-escaped quote inside a
  quoted field (`"x\"y"`) now parses as `x\y"` instead of `x"y`. Doubled quotes
  (`"x""y"`) and plain backslashes (`C:\tmp\file`) are unaffected.

### Internal

- `CsvParser` and `CsvParseOptions` had no tests; they now have 23. Covering them
  is what surfaced both bugs above — the aggregate MSI had been reporting 100%
  while Infection generated no mutants at all for the uncovered class.
- Mutation testing: 795 mutants, 0 escaped.

## [7.0.1] - 2026-09-21

### Fixed

- PHP-CS-Fixer formatting in resilience and utility classes
- PHPStan level=max errors in v7.0 middleware and utilities
- PHP 8.4 compatibility: use readonly properties instead of class-level readonly
- v7.0 namespace and middleware signature corrections

## [7.0.0] - 2026-09-21

### Changed — BREAKING

- **Client registration model changed from global to per-integration.** In v6.0 and earlier, clients were registered globally by adapter type (`integration_engine.client.graphql`, `integration_engine.client.rest`, etc.). In v7.0, each integration gets its own client, automatically wired by the `IntegrationCompilerPass` as `integration_engine.client.{integration_name}`.
  - **Old (v6.0):** `client_service: integration_engine.client.graphql`
  - **New (v7.0):** `client: graphql` with per-integration auto-wiring
  - **Why:** Simpler configuration, consistent middleware chains per integration, better isolation between integrations
  - **Migration:** See [MIGRATION-v7-client-registration.md](./docs/MIGRATION-v7-client-registration.md) for detailed upgrade instructions
- PHP 8.4 is now the minimum target (though bundle works on 8.2+)
- Middleware pipeline refactored: decorators replaced with tagged service middleware via `integration_engine.middleware` priority system
- Request middleware interface: new `RequestMiddlewareInterface` for request signing schemes (OAuth 1.0a, etc.) requiring fully-built request

### Added

- `RequestMiddlewareInterface`: for request-level concerns (signing, custom headers after path resolution)
- `ConnectionResolverInterface`: runtime per-connection resolution (multi-tenant support)
- Per-connection auth token caching via `connectionId`
- Path resolution from `ActionBodyInterface` (body-sourced placeholders)
- `FormEncodedClientAdapter`: for form-encoded request bodies
- Enhanced middleware resolver with priority ordering
- `AdapterMapBuilder` for client adapter discovery

### Fixed

- Middleware composition pipeline now correctly chains user middlewares in declaration order
- GraphQL adapter properly implements `BatchClientInterface` for concurrent requests
- Auth handler token caching respects per-connection discrimination

### Internal

- Mutation testing: 100% MSI over 700+ mutants
- PHPStan level=max passes (0 errors)
- All 601 tests passing across 8 PHP/Symfony version combinations
- Contract test workflow validates compatibility with consuming apps

## [6.0.0] - 2026-09-21

### Removed — BREAKING

- **The webhook reliability scaffolding that only declared intentions is gone.** `WebhookDlqPort`, `WebhookEventAuditPort`, `WebhookMapperResolverPort`, `WebhookFailure`, `WebhookEventState`, `WebhookEventStateTransition`, `WebhookEventRegistry`, `ProcessWebhookMessage` and `ProcessWebhookHandler`: ports nothing called, value objects nothing produced, and a Messenger handler that contradicted [ADR 0008](./docs/adr/0008-no-messenger-bridge-in-the-bundle.md) while duplicating the consumer path. Symfony already hands webhooks to Messenger through `ConsumeRemoteEventMessage`, and its failure transport already is a dead-letter queue. What survived is what computes something: `WebhookIdempotencyService` and `WebhookFingerprinter`, over a `WebhookIdempotencyPort` you implement.
- **The bundle no longer ships integrations for specific providers.** Gone: `ShopifyHmacSignatureVerifier` and `WooCommerceHmacSignatureVerifier` (the same scheme twice — use `Base64HmacSignatureVerifier` with the header as an argument), the two Shopify parsers, the six webhook mappers and six event DTOs, `ShopifyWebhookController`, and the multi-platform routing set (`MultiPlatformWebhookController`, deprecated in 5.4.0, plus `WebhookPlatform`, `WebhookPlatformConfig`, `WebhookPlatformRegistry`). What stays is provider-agnostic: the parser base class, the three signature schemes, the mapper and event contracts, the dispatcher, the consumer trait and the idempotency service. `make:webhook` writes the provider-specific classes into your application instead. See [UPGRADE-6.0.md](./docs/UPGRADE-6.0.md) and [ADR 0014](./docs/adr/0014-no-vendor-integrations-in-the-bundle.md).

### Fixed

- A test in `WebhookIdempotencyTest` pinned "now" to a fixed date while the fake adapter cleaned up against the real clock, so the suite started failing 24 hours later. It now uses a date relative to the clock, like the other webhook tests.
- `IntegrationWebhookRequestParser` no longer maps the payload while parsing. It built the request headers, called `getMapper()->map()` and threw the result away — the `RemoteEvent` has always carried the raw payload, and the real mapping happens in the consumer through `WebhookEventDispatcher`. A provider that sends several event types to one URL (the case WEBHOOK.md tells you to filter in the consumer) made a mapper fail inside the parser, so Symfony answered `500` instead of `406` and the consumer's own type check never ran. Every payload was also mapped twice.

### Added

- `ConsumesWebhookEvents` carries the consumer's `consume()` and its event-type check; override `handles()` for a provider that names the event somewhere other than the payload's `type`.
- `Base64HmacSignatureVerifier`: the raw HMAC-SHA256 digest, base64-encoded and sent whole, named after the scheme rather than after the two providers that use it (whose verifiers duplicate it).
- `make:webhook` takes `hmac_base64` as a verifier type, and generates a parser with **no constructor at all**: the signing secret comes from `framework.webhook.routing.<key>.secret`, so the parser is autowired as it stands and needs no `services.yaml` entry. The timestamped variant only injects a PSR-20 clock, which is autowired too.
- `make:webhook` also generates the consumer, the piece that was missing between Symfony's Messenger and `WebhookEventDispatcher` and that everyone had to copy by hand. It carries `#[AsRemoteEventConsumer]` keyed after the integration and event (`stripe_charge_succeeded`), skips events of another type reaching the same URL, and the command prints the matching `framework.webhook.routing` entry, which uses that same key. Only the listener is left to write.

### Internal

- Mutation testing back over its threshold and then some: 100% covered MSI over 755 mutants, up from 92%. 41 escaped mutants turned into tests (lifecycle event durations and `ActionFailed`, the HMAC prefix check, `SignatureConfig` validation, the webhook mappers' defaults and casts, the request parser's id extraction and POST-only matcher, actions declared after a `webhooks:` block), and the equivalent ones are documented one by one in `docs/advanced/QUALITY.md`.
- Landing page, webhook section: it documented a flow that does not exist — a `webhooks:` YAML block that nothing in the request flow reads, a controller built on the deprecated `MultiPlatformWebhookController`, a `mapper_class` key (it is `mapper`), a mapper missing its `$headers` argument, and business logic inside that mapper. It now shows the real path: `framework.webhook.routing` → a parser extending `IntegrationWebhookRequestParser` → `#[AsRemoteEventConsumer]` → a typed event in an `#[AsEventListener]` listener. Idempotency, DLQ and audit trail are described as contracts you back with your own storage, in both languages. The status block also still announced v5.2.0 and 607 tests; it now reports the current release, test count and mutation score.
- README's status block announced v5.2.0 and listed the 5.2.0 feature set; it now reports v5.4.0, the events that actually reach Symfony listeners, the separate HTTP/mapping timings and the Flex recipe, and describes the webhook flow as parser + consumer + listener.
- Two `infection.json5` ignores still pointed at `IntegrationEngine::dispatchBatch` and `::resolveConnection`, methods that moved to `BatchDispatcher` and `ConnectionResolver` — so the mutants they excused escaped at their new home.

## [5.4.0] - 2026-09-19

### Fixed

- **Lifecycle events now reach Symfony listeners.** `SymfonyEventDispatcherAdapter` couldn't be instantiated (`Cannot call constructor`: it called a parent constructor that doesn't exist), and the bundle never passed a `LifecycleEventDispatcher` to the integrations. The bundle now injects the `IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher` service into every integration; point that service at `SymfonyEventDispatcherAdapter` (as LIFECYCLE.md and the Flex recipe do) and `#[AsEventListener]` listeners receive `ActionStarted`, `ActionCompleted`, etc.
- `HttpResponseReceived::statusCode()` reports the real HTTP status for the built-in REST and GraphQL clients; it was always `0`. The client response shape gains an optional `statusCode` key; a custom client that doesn't set it keeps reporting `0`.
- Flex recipe: drops the unused `INTEGRATION_ENGINE_CACHE` env var, and shows the per-integration options inside an example integration instead of at the root, where they're invalid.

### Changed

- Cache keys for dynamic-auth tokens and cached responses are hashed with `xxh128` instead of `sha1` (non-cryptographic use). After upgrading, tokens and responses cached under the old keys aren't found and are fetched once again.
- `MultiPlatformWebhookController::ingest()` no longer declares the unused `$platform` argument. Callers passing it keep working: PHP accepts extra arguments, and Symfony resolves controller arguments by name.

### Security

- `IntegrationWebhookRequestParser` verified signatures with an empty key when `framework.webhook.routing.<type>.secret` was empty. It now falls back to `getSignatureSecret()`, and rejects the request (`406`) when both are empty.
- `MultiPlatformWebhookController` always verified signatures with an empty key, so it accepted HMACs anyone can compute. It now verifies with `WebhookPlatformConfig::$secret` and answers `500` while none is configured.
- Dead-letter queue failure ids are generated from `random_bytes()` instead of `mt_rand()`.

### Added

- `WebhookPlatformConfig` optional `secret` argument (last position, default `''`).

### Deprecated

- `MultiPlatformWebhookController`: it verifies and acknowledges webhooks but never dispatches them. Use `IntegrationWebhookRequestParser` with Symfony's Webhook component instead (see WEBHOOK.md).

### Internal

- PHPStan level max passes (it reported 59 errors) and php-cs-fixer is clean.
- Contract test workflow: fixed the YAML syntax error that made every run fail instantly, and pointed it at the public demo app (`integrationEngine-demo`, PHP 8.4); the previous target was a private repository the workflow couldn't check out.
- Broken documentation links and stale namespaces fixed; the documentation tests pass again.
- README's webhook feature list now matches what ships: the DLQ, audit trail and idempotency pieces are contracts you provide storage for, and multi-platform routing is deprecated.
- Landing page: code snippets showed PHP namespaces without their backslashes, and 14 snippets never rendered (a span missing its `>`).
- SonarCloud: the analysis config moves to `.sonarcloud.properties`, the only file automatic analysis reads (its exclusions were being ignored, so tests and the landing's i18n counted as duplication). Intentional `composer update` steps and a false positive are annotated, and the remaining issues are fixed.

## [5.3.1] - 2026-09-18

### Fixed

- The bundle no longer autodiscovers `ShopifyWebhookController`, `MultiPlatformWebhookController` and `ProcessWebhookHandler`. They can't be autowired (a secret string; `WebhookMapperResolverPort` / `WebhookDlqPort` with no implementation), and autoconfigure kept them in the container as controller / message handler, so container compilation failed in consuming apps (verified on Symfony 7.4). Register them explicitly if you use them.
- `make:webhook`: the generated parser is no longer `readonly` (fatal error: a readonly class can't extend Symfony's non-readonly `AbstractRequestParser`); the generated namespace now mirrors the generated path (PSR-4 autoloadable); the mapper is generated in its own file.

### Documentation

- WEBHOOK.md rewritten to match the code: Symfony Webhook component + `IntegrationWebhookRequestParser` + `WebhookEventDispatcher`. Removes the nonexistent `webhooks:` bundle config key, `/webhooks/{platform}` endpoint and `webhook:dlq:*` commands; idempotency, DLQ, audit and the Messenger handler are documented as ports without a built-in adapter.

## [5.3.0] - 2026-09-18

### Added

- **`HttpResponseReceived`** lifecycle event: fired after the raw HTTP response, before mapping — tracks HTTP latency on its own.
- **`ResponseMapped`** lifecycle event: fired after DTO mapping, before `ActionCompleted` — carries HTTP, mapping and total durations separately.
- Both are dispatched for direct HTTP calls only, not on the dynamic-auth path. See OBSERVABILITY.md → *Detailed Timing* and ADR 0013.
- **Symfony Flex recipe** (`.symfony-recipes/`), prepared for submission to symfony/recipes-contrib.

### Known issues

- In Symfony apps the bundle doesn't inject `LifecycleEventDispatcher` into the integrations (`IntegrationCompilerPass`), so lifecycle events — including those from 5.2.0 — are not dispatched and `#[AsEventListener]` listeners receive nothing. Events work when `IntegrationEngine` is built by hand with a dispatcher.
- `HttpResponseReceived::$statusCode` is always `0`: clients return only `{body, headers}`.

## [5.2.0] - 2026-09-18

### Added

**Lifecycle Events & Observability** — Production-grade monitoring and debugging capabilities

- **Core events**:
  - **`ActionStarted`**: Fired before HTTP call with action metadata
  - **`ActionCompleted`**: Fired after successful mapping with duration and response
  - **`ActionFailed`**: Fired on errors (HTTP or mapping) with exception details
- **Infrastructure**:
  - **`LifecycleEventDispatcher`**: Low-level event subscription API
  - **`SymfonyEventDispatcherAdapter`**: Bridges bundle events to Symfony EventDispatcher (`#[AsEventListener]`)
  - **`ObservabilitySetup` helper**: One-liner setup for logging, metrics, alerting (recommended)
- **Built-in observability examples**:
  - **Logging**: Custom logger with action metadata (integration name, duration, status)
  - **Prometheus metrics**: HTTP request counter, duration histogram, error rate gauge
  - **Sentry integration**: Capture errors with context (action, integration, response status)
  - **Audit trails**: Immutable event log for compliance and debugging

### Documentation

- **LIFECYCLE.md**: Complete event lifecycle reference
  - Low-level event subscription
  - Symfony EventDispatcher integration
  - Real-world examples (logging, metrics, Sentry)
- **OBSERVABILITY.md**: Quick-start observability setup (recommended)
  - One-liner setup with `ObservabilitySetup`
  - Prometheus metrics export
  - Sentry error tracking
  - Custom handlers for domain events
- **README.md**: Updated status to v5.2.0

### Testing

- **12 lifecycle event tests** (`LifecycleEventDispatcherTest.php`)
  - Event firing, subscription, unsubscription
  - Symfony EventDispatcher adapter
  - Exception handling and propagation
- **All 607 tests passing** (was 595 in v5.1.0)
- **PHPStan level max**: All code type-safe

### Breaking Changes

None. Lifecycle events are opt-in.

### Migration Guide

Existing v5.1.0 users: No action required. Lifecycle events are opt-in.

To add observability to an existing integration:

```php
// Quick start (recommended)
$observability = new ObservabilitySetup(
    logger: $logger,
    prometheusRegistry: $registry,  // optional
    sentryClient: $sentry,           // optional
);
$engine = $observability->setupEngine($config, $client, $cache, $integrationName);

// Or low-level
$dispatcher = new LifecycleEventDispatcher();
$dispatcher->subscribe(ActionCompleted::class, fn($event) => $logger->info(...));
$engine = new IntegrationEngine(..., eventDispatcher: $dispatcher);
```

See OBSERVABILITY.md for step-by-step setup.

### Performance

- **Event dispatch:** < 1ms overhead per action
- **Prometheus metrics export:** < 5ms per scrape
- **Sentry capture:** < 10ms (async in production)

## [5.1.0] - 2026-09-18

### Added

**Multi-Platform Inbound Webhooks** — Production-ready webhook ingestion with reliability & observability

- **`WebhookPlatform` enum**: First-class platform identifiers (SHOPIFY, WOOCOMMERCE, extensible)
- **`WebhookPlatformConfig`**: Bundles verifier + event registry + supported paths
- **`WebhookPlatformRegistry`**: Dynamic platform discovery by path or X-Platform header
- **Multi-platform endpoint**: `/webhooks/{platform}` routes to correct verifier + event registry
  - Shopify: `X-Shopify-Hmac-SHA256` (base64-encoded HMAC)
  - WooCommerce: `X-WC-Webhook-Signature` (base64-encoded HMAC)
  - Extensible: add custom `SignatureVerifierInterface` implementations
- **`ShopifyHmacSignatureVerifier` & `WooCommerceHmacSignatureVerifier`**: Implemented per platform
- **Event DTOs** for Shopify: ProductUpdated, OrderCreated, CustomerUpdated, InventoryUpdated
- **Event DTOs** for WooCommerce: ProductUpdated, OrderCreated
- **`WebhookEventRegistry`**: Maps event type strings to DTO classes, supports multiple registries per platform
- **Idempotency service** (`WebhookIdempotencyService`):
  - Fingerprinting: event type + timestamp + recursive-sorted-payload hash
  - 24-hour retention window
  - Order-invariant hashing (duplicate detection regardless of field order)
- **Dead-letter queue** (`WebhookDlqPort`):
  - Stores failed webhook processing attempts
  - Retry tracking with exponential backoff ready
  - Manual replay via CLI: `webhook:dlq:retry <id>`
- **State machine** (`WebhookEventState` enum):
  - RECEIVED → VALIDATING → PROCESSING → SUCCESS|FAILED|RETRYING
  - Terminal state detection
- **Immutable audit trail** (`WebhookEventStateTransition`):
  - Every state change logged with timestamp + reason + metadata
  - Query by state or transition history
- **Async processing** via Symfony Messenger:
  - `ProcessWebhookMessage` + `ProcessWebhookHandler`
  - Decouples HTTP endpoint from business logic
  - Supports retry policies via transport configuration
- **Mapper resolver** (`WebhookMapperResolverPort`): Extensible event type → mapper lookup

### Documentation

- **WEBHOOK.md**: Complete user guide (47 sections)
  - Quick start: receive, define, map, register, listen
  - Platform integration: add new platform (verifier + registry)
  - Debugging: DLQ, state machine, audit trail, CLI commands
  - Best practices: async first, idempotency, signature validation, domain events
  - Testing: mocking webhooks, fake adapters
  - Architecture: data flow, configuration, environment variables
- **README.md**: Updated status to v5.1.0, highlights new webhook features

### Testing

- **11 platform router tests** (`MultiPlatformWebhookRouterTest.php`)
  - Path detection: `/webhooks/shopify` → Shopify config
  - Header detection: `X-Platform: woocommerce` → WooCommerce config
  - Fallback logic, error cases, platform isolation
- **7 WooCommerce webhook tests** (`WooCommerceWebhookIngestionsTest.php`)
  - Signature validation, payload mapping, minimal payloads, serialization
- **10 idempotency tests** (`WebhookIdempotencyTest.php`)
  - Duplicate detection, order-invariance, cleanup, aging
- **8 DLQ tests** (`WebhookDlqTest.php`)
  - Success/failure paths, retry tracking, failure ordering
- **8 state machine tests** (`WebhookEventStateTransitionTest.php`)
  - Happy path, error transitions, terminal states, history filtering
- **All 595 tests passing** (was 584 in v5.0.0)
- **PHPStan level max**: All code type-safe; no baseline entries

### Breaking Changes

None. Webhook framework is additive; existing API integration features unchanged.

### Migration Guide

Existing v5.0.0 users: No action required. Webhook features are opt-in.

To add webhooks to an existing integration:
1. Implement `SignatureVerifierInterface` for your platform
2. Create event DTOs implementing `WebhookEventInterface`
3. Create mappers extending `AbstractWebhookMapper`
4. Register in `WebhookEventRegistry`
5. Wire into `WebhookPlatformRegistry` with configuration
6. Listen to domain events in your application services

See WEBHOOK.md for step-by-step guide.

### Performance

- **Signature verification**: < 1ms per webhook (HMAC-SHA256)
- **Idempotency check**: < 2ms (Redis or in-memory cache)
- **Fingerprinting**: Order-invariant recursive sort (safe for duplicate detection)
- **Async processing**: HTTP 202 returned immediately; Messenger handles long-running tasks

### Infrastructure

- Adds `WebhookIdempotencyPort` and `WebhookDlqPort` as configurable PSR-6 cache + database adapters
- Supports any PSR-20 clock implementation (default: system clock)
- Messenger support ready (no transport configuration required; uses app transport)

## [5.0.0] - 2026-09-17

### Added

**Inbound Webhooks Framework** — Full support for incoming webhooks from external providers (Stripe, PayPal, etc.)

- **`SignatureVerifierInterface`** and two built-in implementations:
  - `HmacSha256SignatureVerifier`: Simple HMAC-SHA256 with configurable header + prefix
  - `TimestampedHmacSignatureVerifier`: Stripe's model (t=timestamp, v1=hash, v0=old_hash)
- **`WebhookEventInterface`**: Marker for typed webhook event DTOs (must be serializable for Messenger)
- **`AbstractWebhookMapper`**: Base class for webhook payload mappers (mirrors `AbstractMapper` pattern)
- **`IntegrationWebhookRequestParser`**: Extends Symfony's `AbstractRequestParser`
  - Signature verification (HMAC or timestamped)
  - Payload validation (POST + JSON)
  - Mapping to `RemoteEvent` for async processing via Messenger
  - Rejects with HTTP 406 on verification failure
- **`make:webhook` command** for interactive webhook scaffolding:
  ```bash
  php bin/console make:webhook stripe charge.succeeded
  # → Generates: ChargeSucceededEvent + ChargeSucceededRequestParser + Mapper
  ```
- **YAML webhook configuration** in integration config:
  ```yaml
  webhooks:
    charge.succeeded:
      mapper: App\Webhooks\ChargeSucceededMapper
      signature:
        type: timestamped_hmac
        header: Stripe-Signature
  ```
- **Architecture Decision Records**:
  - ADR-0009: Inbound Webhooks Design and Parser Contract
  - ADR-0010: Webhook Idempotency Strategy

### Design

- Webhook signature verification is **mandatory** (security boundary)
- Parser layer is **stateless and fast** (HTTP 202 immediate)
- Idempotency handled in **consumer layer** (at-least-once + idempotent handlers)
- **Compatible with Symfony 6.4+ LTS** and 7.x, 8.x
- Supports **multiple providers** with different signature schemes (extensible via custom verifiers)

### Consequences

- ✅ Webhooks have same structure as outbound integrations (YAML + mapper)
- ✅ Async processing via Messenger bus
- ✅ Type safety: RemoteEvent mapped to WebhookEventInterface (DTO)
- ⚠️ Requires `symfony/webhook` and `symfony/remote-event` (small, stable components)
- ⚠️ Developers implement `AbstractRequestParser::doParse()` for each webhook type (minimal boilerplate)

### Demo Progress (integrationEngine-demo)
- ✅ Days 17-25 Complete: Full multi-protocol demonstration
  - 3 protocols: REST (TMDB), CSV (Supplier), GraphQL (Countries)
  - 40+ tests passing
  - Parallel batch requests (5-13x speedup for 20 items)
  - Middleware extensibility (rate limiting example)
  - Bilingual tour (EN/ES) with 9 live code snippets
  - Architecture: Domain/Application/Infrastructure separation validated
- 🔗 [Live demo repository](https://github.com/CarlosGude/integrationEngine-demo)

### Demo Features Validated
- ✅ Action/Mapper/Response pattern works uniformly across REST, CSV, GraphQL
- ✅ `send()` and `sendMany()` handle parallelism correctly
- ✅ Middleware pipeline extensible without code changes
- ✅ Batch failure handling (null entries, not abort)
- ✅ Parity testing (legacy code produces identical domain results)
- ✅ Live code snippet extraction from marked sections

## [4.1.1] - 2026-09-16

### Added

- Public `ROADMAP.md` with Now/Next/Later sections, no promised dates. Transparency via working software, not calendars.
- Status block in `README.md` linking to roadmap.
- Landing page status section showing project phase and trajectory.

### Fixed

- `make:integration` generated `public const string NAME`, PHP 8.3 syntax,
  even though `composer.json` declares `php >=8.2` — the generated code
  raised a fatal parse error on 8.2 and 8.3-but-not-yet-upgraded projects.
  The generator, `IntegrationName`'s docblock example, and every
  documentation example now use the untyped `public const NAME`.

## [4.1.0] - 2026-09-11

### Changed

- Updated Symfony version constraints to include 6.4+ alongside existing versions (6.4, 7.4, 8.x).
- Added PHPStan Symfony extension for improved static analysis and IDE integration.
- Unified quality gates: MSI ≥ 85%, MSI covered ≥ 95%, PHPStan level max across src and tests.
- Established single CI matrix for all quality checks (no per-job thresholds).

### Added

- `docs/advanced/QUALITY.md`: documented unified quality standards and interpretation of MSI metrics.

## [4.0.0] - 2026-06-28

### Changed

- **BREAKING**: Replaced double-decorator middleware architecture with a unified middleware pipeline.
  - `ClientMiddlewareInterface` renamed to `AbstractClientMiddleware` (abstract base class).
  - `MiddlewareClient` applies middlewares in priority order via DI container tagging.
  - Existing custom middleware must extend `AbstractClientMiddleware` and declare `priority` tag.

- **BREAKING**: `BaseUrlAwareClientInterface` renamed to `DynamicBaseUrlClientInterface`.

- Middleware chaining now uses a single pipeline instead of nested decorators.
  - Batch support via `processMany()` (default passthrough; override when batch-aware behavior needed).

### Added

- Middleware discovery via `integration_engine.middleware` tag with optional `priority` attribute.
- Request middleware support (`RequestMiddlewareInterface`) for signing schemes (OAuth 1.0a, etc.).
- Connection resolver: multi-tenant base URL / authorization per call via `ConnectionResolverInterface`.
- Cache namespacing by `connectionId` for per-connection token isolation.
- Path resolution from request body (body-sourced placeholders consumed before context consulted).

### Fixed

- Middleware ordering now explicit and deterministic via priority system.
- `RetryableHttpClient` properly integrated into pipeline without decorator nesting.

## [3.0.0] - 2026-06-14

### Changed

- **BREAKING**: Batch isolation enforced: each item in `sendMany()` operates independently.
- **BREAKING**: Split `IntegrationEngine` contract classes into dedicated sub-namespaces (`Contract/`).

- Core refactored for clarity:
  - `BatchResultCollection` with `responses()`, `errors()`, `hasFailures()`, `mapWith()`.
  - `AbstractBatchMapper` for second-stage homogeneous batch mapping.
  - `BatchTokenRetry` internal tracking (pre-cached vs. fresh token for 401 retry logic).

### Added

- Action-level mapper validation (invariant: mapper must declare correct action).
- Per-action timeout configuration in YAML.
- Enhanced batch error reporting with `BatchResult::response()` (unwrap) and `error()`.

### Deprecated

- Double-decorator pattern (to be replaced by middleware pipeline in v4.0).

## [2.3.1] - 2026-05-20

### Fixed

- `TraceableClient` and `TraceableBatchClient` now correctly implement `DynamicBaseUrlClientInterface`.

## [2.3.0] - 2026-05-18

### Added

- Symfony Profiler integration: outgoing API calls visible in dev/test with timing, status, action name.
- `DynamicBaseUrlClientInterface`: per-request base URL override in `send()`/`sendMany()`.
- Gateway pattern examples in README and documentation.

### Changed

- Enhanced scaffolding section in README for clarity.

## [2.2.1] - 2026-05-10

### Fixed

- SonarQube integration and code quality cleanup.
- Missing file restoration after quality tool runs.

## [2.2.0] - 2026-05-05

### Added

- `DynamicAuthHandler`: improved token handling with logging and refresh logic.
- Core Contracts reorganized into dedicated sub-namespaces for clarity.

### Fixed

- `FakeLogger` now handles non-string log levels gracefully.

## [2.1.0] - 2026-04-20

### Added

- Batch requests: `sendMany()` method for concurrent execution of mixed actions.
- `BatchResultCollection`: keyed collection with iteration, counting, and `ArrayAccess`.
- Multi-request tests and documentation.

## [2.0.0] - 2026-03-15

### Added

- Initial stable release of IntegrationEngine bundle.
- Core abstractions: `AbstractAction`, `AbstractMapper`, `ResponseInterface`.
- Request/Response separation pattern for all integrations.
- Configuration via YAML (`ConfigPort`, `YamlConfigAdapter`).
- HTTP client abstraction (`ClientInterface`, `SymfonyHttpClientAdapter`).
- Dynamic authentication: token caching and 401 retry with fresh token.
- Middleware system for cross-cutting concerns.
- Comprehensive test suite and documentation.

