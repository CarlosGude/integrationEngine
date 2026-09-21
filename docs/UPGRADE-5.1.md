# Upgrading from v5.0 to v5.1

> **Superseded in 6.0.** The multi-platform pieces described here — `WebhookPlatform`, `WebhookPlatformConfig`, `WebhookPlatformRegistry`, `MultiPlatformWebhookController` — and the shipped Shopify and WooCommerce classes were removed in 6.0. See [UPGRADE-6.0.md](./UPGRADE-6.0.md). The rest of this guide is kept as the record of what 5.1 introduced.

This guide covers the enhancements in v5.1 and how to migrate your webhook integrations.

## Overview of v5.1

v5.1 extends v5.0's webhook framework with:
- **Multi-platform support** (Shopify, WooCommerce, extensible)
- **Idempotency & replay protection** (24-hour fingerprint window)
- **Dead-letter queue** for failed webhooks
- **State machine** for webhook lifecycle tracking
- **Immutable audit trail** for compliance
- **Async processing** via Symfony Messenger

This enables production-grade webhook handling with reliability, observability, and compliance guarantees.

## What's New

### 1. Multi-Platform Webhook Router

v5.1 introduces `WebhookPlatformRegistry` for handling webhooks from multiple providers in a single endpoint.

**Before (v5.0):** One endpoint per provider
```php
#[Route('/webhooks/stripe', methods: ['POST'])]
public function receiveStripe(IntegrationWebhookRequestParser $parser, Request $request): Response { ... }

#[Route('/webhooks/paypal', methods: ['POST'])]
public function receivePaypal(IntegrationWebhookRequestParser $parser, Request $request): Response { ... }
```

**After (v5.1):** One endpoint, multi-platform
```php
#[Route('/webhooks/{platform}', methods: ['POST'])]
public function receiveWebhook(
    MultiPlatformWebhookRouter $router,
    Request $request,
    string $platform,
): Response {
    return $router->route($request, $platform);
}
```

### 2. Built-in Platform Support

v5.1 ships with ready-to-use configurations for:

- **Shopify**
  - Signature: `X-Shopify-Hmac-SHA256` (base64-encoded HMAC)
  - Events: ProductUpdated, OrderCreated, CustomerUpdated, InventoryUpdated
  
- **WooCommerce**
  - Signature: `X-WC-Webhook-Signature` (base64-encoded HMAC)
  - Events: ProductUpdated, OrderCreated

Extend `WebhookPlatformConfig` to add custom platforms.

### 3. Idempotency Service

Prevents duplicate processing of the same webhook.

```php
// Automatically checked by IntegrationWebhookRequestParser
$idempotencyService->isDuplicate(
    eventType: 'order.created',
    timestamp: $webhook['timestamp'],
    payload: $payload
);
```

**Features:**
- Order-invariant recursive hashing (duplicates detected regardless of field order)
- 24-hour retention window (configurable)
- Redis or in-memory cache support

### 4. Dead-Letter Queue (DLQ)

Captures failed webhook processing for later retry or manual review.

**CLI commands:**

```bash
# List failed webhooks
php bin/console webhook:dlq:list

# Retry a failed webhook
php bin/console webhook:dlq:retry <webhook-id>

# Clear old failed webhooks
php bin/console webhook:dlq:clear --older-than=7days
```

**In code:**

```php
// Access DLQ programmatically
$failedWebhooks = $dlqService->all();
$dlqService->retry($webhookId);
```

### 5. Webhook State Machine

Track the lifecycle of each webhook: RECEIVED → VALIDATING → PROCESSING → SUCCESS|FAILED|RETRYING

```php
$stateTransitions = $webhookService->getHistory($webhookId);
foreach ($stateTransitions as $transition) {
    echo "{$transition->from} → {$transition->to} at {$transition->timestamp}";
}
```

### 6. Audit Trail (Immutable)

Every state change is logged with metadata for compliance and debugging.

```php
$auditTrail = $webhookService->getAuditTrail($webhookId);
// Returns: [
//   { state: RECEIVED, reason: 'HTTP 202', timestamp: '...', metadata: {...} },
//   { state: VALIDATING, reason: 'Signature verified', timestamp: '...', ... },
//   { state: PROCESSING, reason: 'Messenger queued', timestamp: '...', ... },
//   { state: SUCCESS, reason: 'Handler completed', timestamp: '...', ... },
// ]
```

### 7. Async Processing via Messenger

Queue webhook processing for later execution instead of blocking the HTTP response.

**Configuration:**

```yaml
integration_engine:
    webhooks:
        async: true  # Optional; default is true
        messenger_transport: 'async'  # Optional; default is app's default transport
```

**In handler:**

```php
#[AsMessageHandler]
class ProcessWebhookHandler
{
    public function __invoke(ProcessWebhookMessage $message): void
    {
        // Long-running business logic here
        $this->orderService->process($message->webhookPayload);
    }
}
```

Returns HTTP 202 Accepted immediately; Messenger handles the payload.

## Migration Path: From v5.0 to v5.1

If you already have v5.0 webhooks, v5.1 is **backward compatible** — no breaking changes.

To adopt v5.1 features:

### Options A and B (removed in 6.0)

v5.1 offered two routes here: a built-in platform (Shopify or WooCommerce) and a custom `WebhookPlatformConfig` registered under the `integration_engine.webhook_platform` tag. Both are gone in 6.0, along with the platform registry and the multi-platform controller — see [UPGRADE-6.0.md](./UPGRADE-6.0.md) for what replaces them.


### Option C: Enable Async Processing

```yaml
integration_engine:
    webhooks:
        async: true
        messenger_transport: 'webhook_transport'  # Define in messenger.yaml
```

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            webhook_transport:
                dsn: 'doctrine://default'  # or redis://, amqp://, etc.
```

## Idempotency Configuration

```yaml
integration_engine:
    webhooks:
        idempotency:
            cache: 'cache.app'  # PSR-6 cache
            ttl: 86400  # 24 hours
            hasher: 'md5'  # or 'sha256', or implement custom
```

## Dead-Letter Queue Configuration

```yaml
integration_engine:
    webhooks:
        dlq:
            cache: 'cache.app'  # PSR-6 cache or database
            retention: 604800  # 7 days
```

## Architecture Decision Records

See:
- `docs/adr/0009-inbound-webhooks.md` — Webhook design (v5.0+)
- `docs/adr/0010-webhook-idempotency.md` — Idempotency strategy (v5.1+)

## Performance Characteristics

- **Signature verification:** < 1ms per webhook
- **Idempotency check:** < 2ms (Redis) or < 5ms (in-memory)
- **State machine insert:** < 1ms
- **Audit trail append:** < 1ms
- **Messenger dispatch:** < 5ms
- **Total HTTP 202 response:** ~10–20ms

## Breaking Changes

**None.** v5.1 is fully backward compatible with v5.0. All new features are opt-in.

## Migration Checklist

- [ ] Update `composer.json` to `^5.1`
- [ ] Run `composer update`
- [ ] Review new configuration options in `WEBHOOK.md`
- [ ] If using multiple platforms, implement or use built-in `WebhookPlatformConfig`
- [ ] If using Messenger, configure webhook transport
- [ ] If using DLQ, configure cache backend
- [ ] Test webhook endpoints with `make:webhook` or curl
- [ ] Review audit trail and state machine in production
- [ ] Set up alerts for DLQ growth (indicates processing failures)

## Monitoring & Observability

Track webhook health:

```php
// In your monitoring/dashboard code
$metrics = $webhookService->metrics();
echo "Last 24h: {$metrics['received']} received, {$metrics['succeeded']} succeeded, {$metrics['failed']} failed";
echo "DLQ size: {$metrics['dlq_count']}";
```

## Support

For questions or issues:

- Complete guide: [`WEBHOOK.md`](./WEBHOOK.md)
- Architecture decisions: [`docs/adr/`](./adr/)
- Performance tuning: [`docs/advanced/debugging.md`](./advanced/debugging.md)
- GitHub discussions: [IntegrationEngine discussions](https://github.com/carlosgude/integrationengine/discussions)
