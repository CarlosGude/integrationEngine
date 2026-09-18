# Webhook Integration Guide

IntegrationEngine v5.1.0 introduces **inbound webhook infrastructure** — receive and process webhooks from external platforms (Shopify, WooCommerce, etc.) with signature verification, idempotency protection, and audit trails.

## Overview

The webhook framework consists of:

- **Platform-agnostic routing**: Single endpoint `/webhooks/{platform}` detects platform from path or header
- **Signature verification**: HMAC-based validation; extensible per platform (Shopify uses base64, WooCommerce uses base64-HMAC-SHA256)
- **Event registry**: Platform-specific mapping of webhook types to typed DTOs
- **Idempotency**: Duplicate detection by event type + timestamp + payload hash; 24h window
- **Async processing**: Messenger integration for non-blocking webhook handling
- **Dead-letter queue**: Failed webhooks stored for manual retry or replay
- **State machine**: Track webhook lifecycle (received → validating → processing → success/failed)
- **Audit trail**: Immutable state transitions for debugging and compliance

## Quick Start

### 1. Receive a Webhook

```php
// POST /webhooks/shopify
// Sends an HTTP 202 Accepted, then processes asynchronously

curl -X POST http://localhost/webhooks/shopify \
  -H "X-Shopify-Hmac-SHA256: <signature>" \
  -H "X-Shopify-Topic: products/update" \
  -d '{"id": 123, "title": "T-Shirt", ...}'
```

Supported platforms: `/webhooks/shopify`, `/webhooks/woocommerce`, or header: `X-Platform: shopify`

### 2. Define an Event DTO

Create a readonly class implementing `WebhookEventInterface`:

```php
<?php
namespace App\Integration\MyPlatform\Webhook;

use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;

final readonly class ProductUpdatedEvent implements WebhookEventInterface
{
    public function __construct(
        public int $productId,
        public string $title,
        public ?float $price,
    ) {}
}
```

### 3. Create a Mapper

Extend `AbstractWebhookMapper` to transform raw payload → typed DTO:

```php
<?php
namespace App\Integration\MyPlatform\Webhook\Mapper;

use App\Integration\MyPlatform\Webhook\ProductUpdatedEvent;
use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;

final class ProductUpdatedMapper extends AbstractWebhookMapper
{
    public function getDefinition(): string
    {
        return 'products/update'; // Platform event type identifier
    }

    public function map(array $payload, array $headers): WebhookEventInterface
    {
        return new ProductUpdatedEvent(
            productId: $payload['id'],
            title: $payload['title'],
            price: isset($payload['price']) ? (float) $payload['price'] : null,
        );
    }
}
```

### 4. Register in Config

Add mappers to `config/integration_engine.yaml`:

```yaml
integration_engine:
    integrations:
        my_platform:
            webhooks:
                - event_type: products/update
                  mapper_class: App\Integration\MyPlatform\Webhook\Mapper\ProductUpdatedMapper
                  signature:
                      type: hmac_sha256
                      header: X-My-Platform-Signature
```

### 5. Listen to Domain Events

```php
<?php
namespace App\EventListener;

use App\Integration\MyPlatform\Webhook\ProductUpdatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class ProductUpdatedListener
{
    #[AsEventListener(event: ProductUpdatedEvent::class)]
    public function onProductUpdated(ProductUpdatedEvent $event): void
    {
        // Handle the event: sync to database, trigger actions, etc.
        logger()->info(sprintf('Product %d updated: %s', $event->productId, $event->title));
    }
}
```

## Adding a New Platform

To integrate webhooks from a new platform (e.g., Shopify, WooCommerce, Stripe):

### 1. Implement a Signature Verifier

```php
<?php
namespace App\Infrastructure\Webhook;

use IntegrationEngine\Core\Contract\Webhook\SignatureVerifierInterface;

final readonly class MyPlatformHmacVerifier implements SignatureVerifierInterface
{
    public function verify(string $body, string $signature, string $secret): bool
    {
        $expectedHash = hash_hmac('sha256', $body, $secret, true);
        $expectedSignature = base64_encode($expectedHash);
        return hash_equals($expectedSignature, $signature);
    }

    public function getHeaderName(): string
    {
        return 'X-My-Platform-Signature';
    }
}
```

### 2. Register Platform in Bootstrap

```php
<?php
namespace App;

use App\Infrastructure\Webhook\MyPlatformHmacVerifier;
use App\Integration\MyPlatform\Webhook\ProductUpdatedMapper;
use IntegrationEngine\Core\Webhook\WebhookPlatform;
use IntegrationEngine\Core\Webhook\WebhookPlatformConfig;
use IntegrationEngine\Infrastructure\Webhook\WebhookEventRegistry;
use IntegrationEngine\Infrastructure\Webhook\WebhookPlatformRegistry;

// In a Symfony command or service:
$registry = new WebhookEventRegistry();
$registry->register('products/update', ProductUpdatedMapper::class);
$registry->register('products/delete', ProductDeletedMapper::class);

$config = new WebhookPlatformConfig(
    platform: WebhookPlatform::from('my_platform'),  // Extend enum if needed
    verifier: new MyPlatformHmacVerifier(),
    eventRegistry: $registry,
    supportedPaths: ['/webhooks/my-platform'],
);

$platformRegistry->register($config);
```

## Debugging & Operations

### Idempotency

Webhooks are deduplicated by fingerprint (event type + timestamp + payload hash). To disable for testing:

```php
// Injected: WebhookIdempotencyService
$service->disableForTest();
```

### Dead-Letter Queue

Failed webhooks are stored in the DLQ. Retrieve and retry:

```bash
# List unresolved failures
php bin/console webhook:dlq:list --unresolved

# Retry a specific failure
php bin/console webhook:dlq:retry <failure-id>

# Retry all failures (after fixing the root cause)
php bin/console webhook:dlq:retry --all
```

### State Machine

Track webhook processing lifecycle:

```php
// Injected: WebhookEventAuditPort
$state = $audit->getCurrentState($webhookId);
echo $state->value;  // e.g., 'success', 'failed'

// View state transitions
$transitions = $audit->getTransitionHistory($webhookId);
foreach ($transitions as $transition) {
    echo sprintf(
        "%s → %s at %s (%s)\n",
        $transition->fromState->value,
        $transition->toState->value,
        $transition->transitionAt->format('c'),
        $transition->reason ?? 'N/A'
    );
}
```

### Audit Trail

All state changes are logged immutably:

```php
$transitions = $audit->getTransitionsFromState($webhookId, WebhookEventState::FAILED);
// Identify all webhooks that failed at step X
```

## Architecture

### Data Flow

```
External platform (Shopify)
    ↓ POST /webhooks/shopify + signature
    ↓
MultiPlatformWebhookController
    ↓ detect platform → get verifier + registry
    ↓
Signature verification (HMAC-SHA256)
    ↓ (async) ProcessWebhookMessage via Messenger
    ↓
WebhookIdempotencyService
    ↓ fingerprint check → skip if duplicate
    ↓
Mapper (resolve by event type)
    ↓ map payload → typed DTO
    ↓
WebhookEventDispatcher
    ↓ dispatch Symfony event
    ↓
Application event listeners
    ↓ handle (sync to DB, trigger workflows, etc.)
    ↓
WebhookEventAuditPort (record SUCCESS state)

On error:
    ↓
WebhookDlqPort (store failure)
    ↓
Retry via CLI or automatic retry handler
```

## Configuration

### Per-Integration Webhook Config (YAML)

```yaml
integration_engine:
    integrations:
        shopify:
            base_url: 'https://shopify.com'
            config_path: '%kernel.project_dir%/config/shopify.yaml'
            webhooks:
                - event_type: products/update
                  mapper_class: App\Shopify\Webhook\Mapper\ProductUpdatedMapper
                  signature:
                      type: hmac_sha256
                      header: X-Shopify-Hmac-SHA256
                - event_type: orders/create
                  mapper_class: App\Shopify\Webhook\Mapper\OrderCreatedMapper
                  signature:
                      type: hmac_sha256
                      header: X-Shopify-Hmac-SHA256
```

### Environment Variables

```bash
# Webhook secrets per integration (loaded at runtime)
WEBHOOK_SHOPIFY_SECRET=whsec_...
WEBHOOK_WOOCOMMERCE_SECRET=wc_...

# Async processing
MESSENGER_TRANSPORT_DSN=doctrine://default  # Store messages in DB
MESSENGER_TRANSPORT_DSN=amqp://...           # Or RabbitMQ

# Cache for auth tokens + idempotency
WEBHOOK_CACHE_SERVICE=cache.app
```

## Best Practices

1. **Async first**: Use Messenger to process webhooks asynchronously. Never block HTTP response.
2. **Idempotency by default**: The framework detects duplicates; assume replays will happen.
3. **Validate signatures**: Always verify HMAC signatures before trusting payload content.
4. **Domain events not DTOs**: Map to domain objects in event listeners, not in mappers. Keep mappers simple.
5. **Test with fixtures**: Record real payloads from platforms; replay in tests with correct signatures.
6. **Monitor DLQ**: Set up alerts for unresolved failures; investigate root causes promptly.
7. **Rotate secrets**: Change webhook secrets regularly; store in secure vaults (not Git).

## Testing

### Mock Webhooks in Tests

```php
<?php
namespace Tests;

use IntegrationEngine\Core\Webhook\ShopifyHmacSignatureVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProductWebhookTest extends WebTestCase
{
    public function testProductUpdatedWebhook(): void
    {
        $payload = [
            'id' => 123,
            'title' => 'Updated Product',
            'price' => 29.99,
        ];

        $verifier = new ShopifyHmacSignatureVerifier();
        $body = json_encode($payload);
        $signature = base64_encode(
            hash_hmac('sha256', $body, 'test_secret', true)
        );

        $client = static::createClient();
        $client->request(
            'POST',
            '/webhooks/shopify',
            [],
            [],
            [
                'HTTP_X_SHOPIFY_HMAC_SHA256' => $signature,
                'HTTP_X_SHOPIFY_TOPIC' => 'products/update',
            ],
            $body
        );

        self::assertResponseStatusCodeSame(202);
    }
}
```

### Fake Adapters in Tests

```php
// tests/Fake/FakeWebhookIdempotencyAdapter.php
final class FakeWebhookIdempotencyAdapter implements WebhookIdempotencyPort
{
    private array $processed = [];

    public function isProcessed(string $fingerprint): bool
    {
        return isset($this->processed[$fingerprint]);
    }

    public function markProcessed(string $fingerprint): void
    {
        $this->processed[$fingerprint] = true;
    }

    public function cleanup(): void
    {
        $this->processed = [];
    }
}
```

## See Also

- **CLAUDE.md**: Architecture overview and command reference
- **README.md**: v5.1.0 release notes
- **tests/Infrastructure/Webhook/**: Functional test examples
- **src/Core/Webhook/**: State machine, DLQ, audit trail contracts
