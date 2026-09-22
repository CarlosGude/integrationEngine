# Upgrade Guide: v7.x → v8.0

IntegrationEngine v8.0 is a major release with **breaking API changes**. This guide covers the migration path for each change.

> **Estimated time:** 2–4 hours per integration, depending on webhook usage and observability setup.

---

## Overview of Breaking Changes

| Change | Impact | Effort |
|---|---|---|
| **Lifecycle events are scalar-only** | Observability subscribers must be rewritten | 30 min per integration |
| **Webhook definitions move to YAML** | Webhook config changes; mappers still extend AbstractWebhookMapper | 45 min per webhook |
| **Form encoding is now declarative** | No code change if using REST; form clients declare `encoding: form` | 15 min |
| **SSRF protection is built-in** | Optional allowlist replaces middleware; private networks blockable | 10 min |
| **Retries and timeouts in config** | `ExponentialBackoffPolicy` becomes optional; config-driven retry rules | 20 min |
| **Legacy webhook idempotency removed** | Applications must implement their own deduplication | 60 min (if used) |
| **PHPStan rules are optional** | Enable new rules in `phpstan.neon` if desired | 5 min |

---

## 1. Lifecycle Events → Scalar-Only Observability

### What Changed

In v7.x, lifecycle events (`ActionStarted`, `ActionCompleted`, `ActionFailed`) carried rich objects:

```php
// v7.x (REMOVED)
$event->action(): AbstractAction
$event->response(): ResponseInterface
$event->error(): Throwable
$event->durationMs(): float
```

In v8.0, events are immutable scalar-only structs. **No secrets leak via subscriber logs.**

### Migration

#### Before (v7.x)
```php
$dispatcher->subscribe(ActionCompleted::class, function(ActionCompleted $event) {
    $logger->info('Action completed', [
        'action' => $event->action()->getName(),
        'response' => get_class($event->response()),
        'duration' => $event->durationMs(),
    ]);
});

$dispatcher->subscribe(ActionFailed::class, function(ActionFailed $event) {
    Sentry\captureException($event->error(), [
        'action' => $event->action()->getName(),
    ]);
});
```

#### After (v8.0)
```php
use IntegrationEngine\Core\Event\ResponseMapped;
use IntegrationEngine\Core\Event\RequestFailed;

$dispatcher->subscribe(ResponseMapped::class, function(ResponseMapped $event) {
    $logger->info('Action completed', [
        'action' => $event->action,                  // string, not object
        'response_type' => $event->responseClass,    // FQN string
        'duration_ms' => $event->durationMs,         // direct property
    ]);
});

$dispatcher->subscribe(RequestFailed::class, function(RequestFailed $event) {
    Sentry\captureMessage($event->message, 'error', [
        'action' => $event->action,
        'exception_class' => $event->exceptionClass, // never the exception itself
    ]);
});
```

### New Event Types

| Event | When | Properties |
|---|---|---|
| `RequestSent` | Before HTTP call | `integrationName`, `action`, `method`, `path`, `timestamp`, `connectionId?`, `requestKey?` |
| `ResponseMapped` | After successful mapping | `integrationName`, `action`, `durationMs`, `statusCode`, `responseClass`, `timestamp`, `requestKey?` |
| `RequestFailed` | On error | `integrationName`, `action`, `durationMs`, `statusCode`, `exceptionClass`, `message`, `timestamp`, `requestKey?` |
| `TokenRefreshed` | Dynamic auth refresh | `integrationName`, `action`, `timestamp` |
| `WebhookReceived` | Webhook verified | `integrationName`, `eventType`, `timestamp` |
| `WebhookRejected` | Webhook rejected | `integrationName`, `eventType`, `timestamp`, `reason?` |

### Observability Setup

If using the `ObservabilitySetup` helper (recommended):

```php
// v8.0 - Update callback signatures
ObservabilitySetup::register($dispatcher, $logger, [
    'logging' => true,
    'slow_request_threshold_ms' => 3000,
    'metrics_callback' => function($event) {
        // $event is ResponseMapped or RequestFailed
        // Use $event->durationMs, $event->action, $event->statusCode, etc.
    },
    'error_callback' => function(RequestFailed $event) {
        // $event->message is the error message, never the exception
    },
]);
```

---

## 2. Webhook Definitions → YAML

### What Changed

In v7.x, webhook configuration was scattered: mappers, parser setup, and signature verification in code.

In v8.0, **each integration declares webhooks once in YAML**, and the engine wires everything.

### Migration

#### Before (v7.x)
```php
// Parser and mapper setup scattered across code
$parser = new IntegrationWebhookRequestParser(
    new YamlConfigAdapter($configPath),
    new HmacSha256SignatureVerifier('secret'),
    $logger
);

$event = $parser->parse($request, $secret);
if ($event) {
    $response = EventHandler::handle($event);
}
```

#### After (v8.0)

**1. Declare webhooks in your integration YAML:**

```yaml
# src/Infrastructure/Integrations/Stripe/Stripe.yaml
get_charge:
  action: GetChargeAction
  # ...

webhooks:
  type_field: type
  id_field: id
  signature:
    type: hmac_sha256
    header: X-Stripe-Signature
    secret: '%env(WEBHOOK_SECRET)%'
  unknown_events: ignore  # or 'reject'
  events:
    charge.succeeded:
      mapper: ChargeSucceededMapper
    charge.failed:
      mapper: ChargeFailedMapper
```

**2. Mapper still extends `AbstractWebhookMapper`:**

```php
// No change from v7.x structure
use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;

final class ChargeSucceededMapper extends AbstractWebhookMapper
{
    public static function eventType(): string
    {
        return 'charge.succeeded';
    }

    protected static function transform(array $payload, array $headers): WebhookEventInterface
    {
        return new ChargeSucceededEvent(
            chargeId: $payload['id'],
            amount: $payload['amount'],
        );
    }
}
```

**3. The bundle wires the parser automatically.** No manual setup needed:

```php
// In your controller or message handler
use IntegrationEngine\Infrastructure\Webhook\IntegrationWebhookRequestParser;
use IntegrationEngine\Infrastructure\Webhook\WebhookRejectedException;

#[AsRemoteEventConsumer(service: 'shopify')]
public function onWebhook(RemoteEvent $event): void
{
    // The event is already mapped and verified by the engine
    // Handle your business logic
}
```

### Signature Types

Supported in YAML:

```yaml
# HMAC SHA256
signature:
  type: hmac_sha256
  header: X-Signature
  secret: '%env(WEBHOOK_SECRET)%'

# Base64-encoded HMAC
signature:
  type: base64_hmac
  header: X-Signature
  secret: '%env(WEBHOOK_SECRET)%'

# Timestamped HMAC (with replay protection)
signature:
  type: timestamped_hmac
  header: X-Signature
  secret: '%env(WEBHOOK_SECRET)%'
  tolerance: 300  # seconds
```

### Unknown Events Policy

Configure what happens when an unknown event type is received:

```yaml
webhooks:
  unknown_events: ignore   # Accept with HTTP 202 (safe)
  # OR
  unknown_events: reject   # Reject with HTTP 406
```

---

## 3. Form Encoding is Now Declarative

### What Changed

In v7.x, form encoding required custom client registration or middleware.

In v8.0, declare encoding in action YAML: `encoding: form` or default JSON.

### Migration

#### Before (v7.x)
```yaml
# Manual client or middleware needed
get_user:
  action: GetUserAction
  client: form_encoded  # Custom registration
```

#### After (v8.0)
```yaml
get_user:
  action: GetUserAction
  encoding: form  # Built-in; no registration needed
  # OR omit for JSON (default)
```

### Batch Behavior

Batches now preserve encoding per action:

```php
// v8.0 - mixed batch of JSON and form
$results = $engine->sendMany([
    'json_action' => new EngineRequest('GetJson'),    // uses JSON
    'form_action' => new EngineRequest('GetForm'),    // uses form encoding
]);
```

---

## 4. SSRF Protection is Built-In

### What Changed

In v7.x, SSRF protection required middleware.

In v8.0, the engine blocks disallowed hosts by default. Configure allowlists in integration config.

### Migration

#### Before (v7.x)
```yaml
# Manual middleware needed
integration_engine:
  integrations:
    shopify:
      base_url: 'https://api.shopify.com'
      middlewares:
        - my_security_middleware
```

#### After (v8.0)
```yaml
# No middleware needed; use built-in allowlist
integration_engine:
  integrations:
    shopify:
      base_url: 'https://api.shopify.com'
      transport:
        allowed_hosts:
          - 'api.shopify.com'
          - '*.shopify.com'           # wildcards supported
        block_private_networks: true  # optional: block 10.0.0.0/8, 172.16.0.0/12, etc.
```

**Result:** All requests to disallowed hosts are rejected before sending. Improves security without custom middleware.

---

## 5. Retries & Timeouts Are Declarative

### What Changed

In v7.x, retry logic was manual: `ExponentialBackoffPolicy` + custom orchestration.

In v8.0, retries and timeouts are configuration-driven. `ExponentialBackoffPolicy` is optional.

### Migration

#### Before (v7.x)
```php
// Application-level retry logic
$backoff = new ExponentialBackoffPolicy(maxRetries: 3);
try {
    $response = $engine->send('get_user', $context);
} catch (RequestResponseException $e) {
    if ($backoff->shouldRetry($e)) {
        sleep($backoff->nextDelayMs() / 1000);
        $response = $engine->send('get_user', $context);
    }
}
```

#### After (v8.0)
```yaml
# Retries are configuration-driven
integration_engine:
  integrations:
    shopify:
      base_url: 'https://api.shopify.com'
      transport:
        retry:
          max_retries: 3
          delay_ms: 100
          multiplier: 2.0
          max_delay_ms: 5000
          jitter: 0.1
          status_codes: [429, 503]  # retry on these
          retry_non_idempotent: false
        timeout: 30.0  # seconds, supports fractional (30.5)
```

**No application code needed** — the engine retries automatically.

### Timeout Validation

Timeouts are now validated at config load time:

```yaml
# Valid:
timeout: 30
timeout: 30.5
timeout: 0  # No timeout

# Invalid (rejected):
timeout: -1        # Error: "timeout must be a finite non-negative number"
timeout: .Inf      # Error: "timeout must be a finite non-negative number"
timeout: .NaN      # Error: "timeout must be a finite non-negative number"
```

---

## 6. Legacy Webhook Idempotency Removed

### What Changed

In v7.x, the engine provided `WebhookIdempotencyService` + `WebhookFingerprinter` for deduplication.

In v8.0, these are **removed**. Applications must implement their own.

### Migration

If you were using `WebhookIdempotencyService`:

```php
// v7.x (REMOVED)
$service = $container->get(WebhookIdempotencyService::class);
if ($service->isProcessed($webhook)) {
    return 202; // Already handled
}
```

**Implement your own:**

```php
// v8.0 - Application-owned idempotency
class WebhookDeduplication
{
    public function __construct(private PDO $db) {}

    public function isProcessed(string $eventId, string $integrationName): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM webhook_events WHERE event_id = ? AND integration = ?'
        );
        return (bool) $stmt->execute([$eventId, $integrationName])->fetch();
    }

    public function mark(string $eventId, string $integrationName): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO webhook_events (event_id, integration) VALUES (?, ?)'
        );
        $stmt->execute([$eventId, $integrationName]);
    }
}

// In your webhook handler:
#[AsRemoteEventConsumer(service: 'shopify')]
public function onWebhook(RemoteEvent $event): void
{
    if ($this->dedup->isProcessed($event->getId(), 'shopify')) {
        return; // Already processed
    }

    // Handle webhook...

    $this->dedup->mark($event->getId(), 'shopify');
}
```

**Why the change?** Idempotency is application-specific. Your deduplication key, TTL, and storage (Redis, DB, etc.) depend on your domain. The engine can't know the right strategy for every app.

---

## 7. Optional PHPStan Rules for Type Safety

### What Changed

Three new optional PHPStan rules validate integration contracts at build time.

### Migration

If you want static verification of response types, enable in `phpstan.neon`:

```neon
# phpstan.neon
includes:
  - vendor/php-http/client-integration/phpstan-rules.neon

rules:
  integration_engine.rules.mapper_action: true          # Validate mapper/action pairing
  integration_engine.rules.response_class_modifiers: true # Responses must be final/readonly
  integration_engine.rules.integration_facade_return_type: true # Facade return types match
```

**Optional.** Your code works fine without these rules; they're for teams that want extra type safety.

---

## 8. Request Middleware (New in v8.0)

### What Changed

**New:** `RequestMiddlewareInterface` for signing requests (OAuth 1.0a, etc.) that need the fully-built request.

This is **not a breaking change**, but a new feature for advanced users.

### Usage

```php
use IntegrationEngine\Core\Contract\Client\RequestMiddlewareInterface;
use IntegrationEngine\Core\Contract\Client\Request;
use Symfony\Contracts\HttpClient\ResponseInterface;

class Oauth1SigningMiddleware implements RequestMiddlewareInterface
{
    public function process(Request $request, callable $next): ResponseInterface
    {
        // Mutate request URL, headers, body to sign it
        $signed = $this->signRequest($request);
        
        return $next($signed);
    }
}

// Register in YAML:
# integration_engine.yaml
integration_engine:
  integrations:
    twitter:
      base_url: 'https://api.twitter.com'
      request_middlewares:
        - my_oauth1_middleware
```

---

## 9. Configuration Schema Changes

### Top-level `transport` config

All retry/timeout/security settings now go under `transport`:

```yaml
integration_engine:
  integrations:
    shopify:
      base_url: 'https://...'
      
      # NEW: transport config groups retry, timeout, SSRF
      transport:
        timeout: 30.0
        retry:
          max_retries: 3
          delay_ms: 100
          multiplier: 2.0
          max_delay_ms: 5000
          jitter: 0.1
          status_codes: [429, 503]
          retry_non_idempotent: false
        allowed_hosts:
          - 'api.shopify.com'
        block_private_networks: true
        request_middlewares:
          - my_signing_middleware
```

### Webhook config in YAML actions

Webhooks are now declared per-integration in the action YAML file (not generated):

```yaml
# src/Infrastructure/Integrations/Stripe/Stripe.yaml

webhooks:
  type_field: type
  id_field: id
  signature:
    type: hmac_sha256
    header: X-Stripe-Signature
    secret: '%env(WEBHOOK_SECRET)%'
  unknown_events: ignore
  events:
    charge.succeeded:
      mapper: App\Stripe\Webhook\ChargeSucceededMapper
    charge.failed:
      mapper: App\Stripe\Webhook\ChargeFailedMapper
```

---

## 10. Checklist for Migrating an Integration

- [ ] **Observability:** Update `$dispatcher->subscribe()` callbacks to use new event classes and properties
- [ ] **Webhooks:** Move webhook config to action YAML; update handler to use Symfony's `RemoteEvent` interface
- [ ] **Encoding:** Add `encoding: form` to actions that need it (or omit for JSON default)
- [ ] **SSRF:** Add `transport.allowed_hosts` allowlist to integration config
- [ ] **Retries/Timeouts:** Move retry logic to `transport.retry` and `transport.timeout` config
- [ ] **Idempotency:** If using webhook dedup, implement application-owned version
- [ ] **Testing:** Run full test suite; ensure observability and webhook handlers still work
- [ ] **PHPStan:** Optionally enable new rules in `phpstan.neon`

---

## 11. Need Help?

- **Lifecycle events:** See [LIFECYCLE.md](./LIFECYCLE.md) and [OBSERVABILITY.md](./OBSERVABILITY.md)
- **Webhooks:** See [WEBHOOK.md](./WEBHOOK.md) and [webhooks-v8.md](./webhooks-v8.md)
- **Configuration:** See [CLAUDE.md](../CLAUDE.md) Bundle Configuration section
- **Tests:** Check `tests/Infrastructure/Webhook/` and `tests/Core/Event/` for examples

---

## Summary

v8.0 **strengthens security, simplifies configuration, and reduces boilerplate**:

| Before | After |
|---|---|
| Rich event objects (leak risk) | Scalar-only events (safe) |
| Scattered webhook config | Unified YAML declarations |
| Manual retry orchestration | Config-driven retries |
| Custom SSRF middleware | Built-in allowlist protection |
| Application-guessed idempotency | Explicit, application-owned dedup |

Most integrations can be upgraded in **under 4 hours**. Start with observability, then webhooks, then retries.

**Note:** The demo app (integrationEngine-demo repo) has been updated to v8.0 webhook architecture for reference.
