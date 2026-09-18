# Changelog

All notable changes to this project are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [5.2.0] - 2026-09-18

### Added

**Lifecycle Events & Observability** — Production-grade monitoring and debugging capabilities

- **Core events**:
  - **`ActionStarted`**: Fired before HTTP call with action metadata
  - **`HttpResponseReceived`**: Fired after raw HTTP response (new in 5.2.0) — track API latency separately
  - **`ResponseMapped`**: Fired after DTO mapping (new in 5.2.0) — break down HTTP vs. transformation time
  - **`ActionCompleted`**: Fired after successful mapping with duration and response
  - **`ActionFailed`**: Fired on errors (HTTP or mapping) with exception details
- **Infrastructure**:
  - **`LifecycleEventDispatcher`**: Low-level event subscription API
  - **`SymfonyEventDispatcherAdapter`**: Bridges bundle events to Symfony EventDispatcher (`#[AsEventListener]`)
  - **`ObservabilitySetup` helper**: One-liner setup for logging, metrics, alerting (recommended)
- **Built-in observability examples**:
  - **Logging**: Custom logger with action metadata (integration name, duration, status)
  - **Prometheus metrics**: HTTP request counter, duration histogram, error rate gauge
  - **Detailed timing**: Separate gauges for HTTP latency, DTO mapping time, overhead
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

- `docs/QUALITY.md`: documented unified quality standards and interpretation of MSI metrics.

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

