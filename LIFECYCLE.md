# IntegrationEngine · Lifecycle Events

Tap into integration lifecycle events for logging, metrics, debugging, and observability.

**Quick start:** See [OBSERVABILITY.md](./OBSERVABILITY.md) for ready-made helpers (recommended).

---

## Low-level: Direct Event Subscription

---

## Events

The engine fires three events at key points:

| Event | When | Data |
|-------|------|------|
| `ActionStarted` | Before HTTP call | action, integration name, timestamp |
| `ActionCompleted` | After successful mapping | action, response DTO, duration, timestamp |
| `ActionFailed` | On error (HTTP or mapping) | action, exception, duration, timestamp |

---

## Setup

### Option 1: Built-in Dispatcher

```php
use IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher;
use IntegrationEngine\Core\Lifecycle\ActionCompleted;

$dispatcher = new LifecycleEventDispatcher();

// Subscribe to an event
$dispatcher->subscribe(ActionCompleted::class, function(ActionCompleted $event) {
    echo "Action {$event->action()->getName()} completed in {$event->durationMs()}ms\n";
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

If your app already uses Symfony events, use the adapter:

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
use IntegrationEngine\Core\Lifecycle\ActionCompleted;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ActionCompleted::class)]
public function onActionCompleted(ActionCompleted $event): void
{
    echo "Completed: {$event->action()->getName()}\n";
}
```

---

## Real-world Examples

### 1. Custom Logging

Log each action with domain context:

```php
$dispatcher->subscribe(ActionCompleted::class, function(ActionCompleted $event) {
    $this->logger->info('Integration action succeeded', [
        'integration' => $event->integrationName(),
        'action' => $event->action()->getName(),
        'duration_ms' => $event->durationMs(),
        'response_type' => $event->response()::class,
    ]);
});

$dispatcher->subscribe(ActionFailed::class, function(ActionFailed $event) {
    $this->logger->error('Integration action failed', [
        'integration' => $event->integrationName(),
        'action' => $event->action()->getName(),
        'duration_ms' => $event->durationMs(),
        'error' => $event->error()->getMessage(),
    ]);
});
```

### 2. Prometheus Metrics

Export timing and error counters:

```php
use Prometheus\CollectorRegistry;

$registry = new CollectorRegistry();
$histogram = $registry->registerHistogram(
    'integration_engine',
    'action_duration_ms',
    'Action execution time',
    ['integration', 'action', 'status']
);

$dispatcher->subscribe(ActionCompleted::class, function(ActionCompleted $event) use ($histogram) {
    $histogram
        ->labels($event->integrationName(), $event->action()->getName(), 'success')
        ->observe($event->durationMs());
});

$dispatcher->subscribe(ActionFailed::class, function(ActionFailed $event) use ($histogram) {
    $histogram
        ->labels($event->integrationName(), $event->action()->getName(), 'failure')
        ->observe($event->durationMs());
});
```

### 3. Alerting on Slow Requests

Notify ops if an integration is slow:

```php
$dispatcher->subscribe(ActionCompleted::class, function(ActionCompleted $event) {
    if ($event->durationMs() > 5000) {
        $this->slack->notify([
            'channel' => '#alerts',
            'text' => "⚠️ Slow integration: {$event->integrationName()} "
                . "{$event->action()->getName()} took {$event->durationMs()}ms",
        ]);
    }
});
```

### 4. Error Tracking (Sentry)

Send errors to your error tracker:

```php
use Sentry\captureException;

$dispatcher->subscribe(ActionFailed::class, function(ActionFailed $event) {
    captureException($event->error(), [
        'tags' => [
            'integration' => $event->integrationName(),
            'action' => $event->action()->getName(),
        ],
        'extra' => [
            'duration_ms' => $event->durationMs(),
        ],
    ]);
});
```

### 5. Audit Trail

Log all integrations to a database for compliance:

```php
$dispatcher->subscribe(ActionCompleted::class, function(ActionCompleted $event) {
    $this->db->insert('integration_audit_log', [
        'integration' => $event->integrationName(),
        'action' => $event->action()->getName(),
        'status' => 'success',
        'duration_ms' => $event->durationMs(),
        'timestamp' => date('Y-m-d H:i:s', $event->timestamp()),
    ]);
});
```

---

## Event Structure

### ActionStarted

```php
$event->action(): AbstractAction
$event->integrationName(): string    // e.g., 'shopify'
$event->timestamp(): float           // microtime(true)
```

### ActionCompleted

```php
$event->action(): AbstractAction
$event->integrationName(): string
$event->timestamp(): float
$event->response(): ResponseInterface  // Your typed DTO
$event->durationMs(): float
```

### ActionFailed

```php
$event->action(): AbstractAction
$event->integrationName(): string
$event->timestamp(): float
$event->error(): \Throwable           // The exception
$event->durationMs(): float
```

---

## Best Practices

- **Keep subscribers fast.** They run in the critical path.
- **Use ActionStarted sparingly.** For correlation IDs, not heavy logging.
- **Catch exceptions in subscribers.** If a subscriber throws, the engine doesn't catch it; make sure your listeners are defensive.
- **Batch logging.** If you log every action, buffer and flush to avoid I/O overhead.

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
