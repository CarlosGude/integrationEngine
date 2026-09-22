# IntegrationEngine · Lifecycle Events

Tap into integration lifecycle events for logging, metrics, debugging, and observability.

**Quick start:** See [OBSERVABILITY.md](./OBSERVABILITY.md) for ready-made helpers (recommended).

---

## Low-level: Direct Event Subscription

---

## Events

The engine fires events at key points:

| Event | When | Data |
|-------|------|------|
| `RequestSent` | Before HTTP call | integration name, action, method, path, timestamp |
| `ResponseMapped` | After successful mapping | integration name, action, duration, status code, response class, timestamp |
| `RequestFailed` | On error (HTTP or mapping) | integration name, action, duration, status code, exception class, message, timestamp |
| `TokenRefreshed` | After dynamic auth refresh | integration name, action, timestamp |
| `WebhookReceived` | Webhook signature verified | integration name, event type, timestamp |
| `WebhookRejected` | Webhook rejected | integration name, event type, reason, timestamp |

---

## Setup

### Option 1: Built-in Dispatcher

```php
use IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher;
use IntegrationEngine\Core\Event\ResponseMapped;

$dispatcher = new LifecycleEventDispatcher();

// Subscribe to an event
$dispatcher->subscribe(ResponseMapped::class, function(ResponseMapped $event) {
    echo "Action {$event->action} completed in {$event->durationMs}ms\n";
});

// Pass dispatcher to engine
$engine = new IntegrationEngine(
    config: $config,
    client: $client,
    cache: $cache,
    integrationName: 'shopify',
    eventDispatcher: $dispatcher,
);
```

### Option 2: Symfony EventDispatcher

If your app already uses Symfony events, use the adapter. The bundle passes this service to every integration (5.3.2+; earlier versions never injected it, so listeners received nothing):

```yaml
# services.yaml
services:
  IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher:
    class: IntegrationEngine\Infrastructure\Lifecycle\SymfonyEventDispatcherAdapter
    arguments:
      - '@event_dispatcher'
```

Then use `#[AsEventListener]` or `services.yaml` to listen:

```php
use IntegrationEngine\Core\Event\ResponseMapped;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ResponseMapped::class)]
public function onResponseMapped(ResponseMapped $event): void
{
    echo "Completed: {$event->action}\n";
}
```

---

## Real-world Examples

### 1. Custom Logging

Log each action with domain context:

```php
use IntegrationEngine\Core\Event\ResponseMapped;
use IntegrationEngine\Core\Event\RequestFailed;

$dispatcher->subscribe(ResponseMapped::class, function(ResponseMapped $event) {
    $this->logger->info('Integration action succeeded', [
        'integration' => $event->integrationName,
        'action' => $event->action,
        'duration_ms' => $event->durationMs,
        'response_type' => $event->responseClass,
    ]);
});

$dispatcher->subscribe(RequestFailed::class, function(RequestFailed $event) {
    $this->logger->error('Integration action failed', [
        'integration' => $event->integrationName,
        'action' => $event->action,
        'duration_ms' => $event->durationMs,
        'error' => $event->message,
    ]);
});
```

### 2. Prometheus Metrics

Export timing and error counters:

```php
use Prometheus\CollectorRegistry;
use IntegrationEngine\Core\Event\ResponseMapped;
use IntegrationEngine\Core\Event\RequestFailed;

$registry = new CollectorRegistry();
$histogram = $registry->registerHistogram(
    'integration_engine',
    'action_duration_ms',
    'Action execution time',
    ['integration', 'action', 'status']
);

$dispatcher->subscribe(ResponseMapped::class, function(ResponseMapped $event) use ($histogram) {
    $histogram
        ->labels($event->integrationName, $event->action, 'success')
        ->observe($event->durationMs);
});

$dispatcher->subscribe(RequestFailed::class, function(RequestFailed $event) use ($histogram) {
    $histogram
        ->labels($event->integrationName, $event->action, 'failure')
        ->observe($event->durationMs);
});
```

### 3. Alerting on Slow Requests

Notify ops if an integration is slow:

```php
use IntegrationEngine\Core\Event\ResponseMapped;

$dispatcher->subscribe(ResponseMapped::class, function(ResponseMapped $event) {
    if ($event->durationMs > 5000) {
        $this->slack->notify([
            'channel' => '#alerts',
            'text' => "⚠️ Slow integration: {$event->integrationName} "
                . "{$event->action} took {$event->durationMs}ms",
        ]);
    }
});
```

### 4. Error Tracking (Sentry)

Send errors to your error tracker:

```php
use Sentry\captureMessage;
use IntegrationEngine\Core\Event\RequestFailed;

$dispatcher->subscribe(RequestFailed::class, function(RequestFailed $event) {
    captureMessage($event->message, 'error', [
        'tags' => [
            'integration' => $event->integrationName,
            'action' => $event->action,
            'exception' => $event->exceptionClass,
        ],
        'extra' => [
            'duration_ms' => $event->durationMs,
            'status_code' => $event->statusCode,
        ],
    ]);
});
```

### 5. Audit Trail

Log all integrations to a database for compliance:

```php
use IntegrationEngine\Core\Event\ResponseMapped;

$dispatcher->subscribe(ResponseMapped::class, function(ResponseMapped $event) {
    $this->db->insert('integration_audit_log', [
        'integration' => $event->integrationName,
        'action' => $event->action,
        'status' => 'success',
        'duration_ms' => $event->durationMs,
        'timestamp' => date('Y-m-d H:i:s', $event->timestamp),
    ]);
});
```

---

## Event Structure

### RequestSent

```php
$event->integrationName: string    // e.g., 'shopify'
$event->action: string             // Action name
$event->method: string             // HTTP method
$event->path: string               // Resolved path
$event->timestamp: float           // microtime(true)
$event->connectionId: ?string      // Optional connection identifier
$event->requestKey: int|string|null // Batch request key, if applicable
```

### ResponseMapped

```php
$event->integrationName: string
$event->action: string
$event->durationMs: float          // Milliseconds elapsed
$event->statusCode: int            // HTTP status code
$event->responseClass: string      // FQN of response DTO
$event->timestamp: float
$event->requestKey: int|string|null
```

### RequestFailed

```php
$event->integrationName: string
$event->action: string
$event->durationMs: float
$event->statusCode: int            // HTTP status, or 0 if no response
$event->exceptionClass: string     // FQN of exception thrown
$event->message: string            // Exception message
$event->timestamp: float
$event->requestKey: int|string|null
```

### TokenRefreshed

```php
$event->integrationName: string
$event->action: string             // Token action name
$event->timestamp: float
```

### WebhookReceived / WebhookRejected

```php
$event->integrationName: string
$event->eventType: string          // Webhook event type
$event->timestamp: float
$event->reason: ?string            // Rejection reason (WebhookRejected only)
```

---

## Best Practices

- **Keep subscribers fast.** They run in the critical path.
- **Use RequestSent sparingly.** For correlation IDs, tracing spans, or request counting — not heavy logging.
- **Catch exceptions in subscribers.** If a subscriber throws, the engine doesn't catch it; make sure your listeners are defensive.
- **Batch logging.** If you log every request, buffer and flush to avoid I/O overhead.
- **Never assume response/exception access.** Events carry only metadata (status codes, class names, messages), not the objects themselves — design your observability around primitives.

---

## No Overhead if Unused

If no dispatcher is passed to the engine, events are not created. There is zero performance cost.

```php
// No events, no overhead
$engine = new IntegrationEngine(
    config: $config,
    client: $client,
    cache: $cache,
    integrationName: 'shopify',
    // eventDispatcher: null (default)
);
```
