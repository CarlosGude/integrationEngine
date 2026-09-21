# IntegrationEngine · Observability Setup

Quick integration of logging, metrics, and alerting for your integrations using lifecycle events.

---

## Quick Start

### 1. Register Observability in services.yaml

```yaml
services:
  # Event dispatcher
  IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher:
    class: IntegrationEngine\Infrastructure\Lifecycle\SymfonyEventDispatcherAdapter
    arguments:
      - '@event_dispatcher'

  # Observability setup for Shopify
  app.shopify.observability:
    class: IntegrationEngine\Infrastructure\Lifecycle\ObservabilitySetup
    arguments:
      - '@IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher'
      - '@logger'
      - integration: 'shopify'
        logging: true
        log_level: 'info'
        slow_request_threshold_ms: 3000
    calls:
      - [register, ['@IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher', '@logger', []]]
```

### 2. Use the engine with dispatcher

```php
// In your integration service
class ShopifyIntegrationService
{
    public function __construct(
        private IntegrationEngine $engine,
        private LifecycleEventDispatcher $dispatcher,
    ) {
        // Pass dispatcher to engine
        // (already injected via DI in services.yaml)
    }

    public function syncProduct(int $productId): void
    {
        // Events fire automatically
        $product = $this->engine->send('GetProduct', 
            context: new Context(['id' => $productId])
        );
        
        // Logging, alerts, metrics all happen automatically ✓
    }
}
```

---

## Real-world Example: Shopify Product Sync

### Setup

```yaml
# config/services.yaml
services:
  # Dispatcher (once per app)
  IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher:
    class: IntegrationEngine\Infrastructure\Lifecycle\SymfonyEventDispatcherAdapter
    arguments:
      - '@event_dispatcher'

  # Shopify observability
  app.shopify.observability:
    class: App\Integration\Shopify\ShopifyObservabilitySetup
    calls:
      - [register, ['@IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher', '@logger']]
```

### Custom Observability Class

```php
// src/Integration/Shopify/ShopifyObservabilitySetup.php
namespace App\Integration\Shopify;

use IntegrationEngine\Core\Lifecycle\ActionFailed;
use IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher;
use Psr\Log\LoggerInterface;
use Sentry;

class ShopifyObservabilitySetup
{
    public function __construct(
        private LoggerInterface $logger,
        private SlackNotifier $slack,
        private PrometheusRegistry $prometheus,
    ) {}

    public function register(LifecycleEventDispatcher $dispatcher): void
    {
        // Logging (built-in)
        \IntegrationEngine\Infrastructure\Lifecycle\ObservabilitySetup::register(
            $dispatcher,
            $this->logger,
            [
                'logging' => true,
                'log_level' => 'info',
                'slow_request_threshold_ms' => 3000,
                'integration_filter' => 'shopify',
            ]
        );

        // Custom metrics (Prometheus)
        \IntegrationEngine\Infrastructure\Lifecycle\ObservabilitySetup::register(
            $dispatcher,
            $this->logger,
            [
                'metrics_callback' => fn($event) => $this->recordMetrics($event),
                'integration_filter' => 'shopify',
            ]
        );

        // Custom error handling (Sentry)
        \IntegrationEngine\Infrastructure\Lifecycle\ObservabilitySetup::register(
            $dispatcher,
            $this->logger,
            [
                'error_callback' => fn($event) => $this->recordError($event),
                'integration_filter' => 'shopify',
            ]
        );
    }

    private function recordMetrics($event): void
    {
        // Prometheus histogram: duration by action
        $this->prometheus->histogram(
            'shopify_api_duration_ms',
            $event->durationMs(),
            [
                'action' => $event->action()->getName(),
                'status' => $event instanceof ActionFailed ? 'failed' : 'success',
            ]
        );

        // Alert Slack if slow
        if ($event->durationMs() > 3000) {
            $this->slack->alert(
                "🐢 Shopify {$event->action()->getName()} was slow: {$event->durationMs()}ms"
            );
        }
    }

    private function recordError(ActionFailed $event): void
    {
        // Send to Sentry with context
        Sentry\captureException($event->error(), [
            'tags' => [
                'integration' => 'shopify',
                'action' => $event->action()->getName(),
            ],
            'extra' => [
                'duration_ms' => $event->durationMs(),
            ],
        ]);

        // Slack critical alert
        $this->slack->critical(
            "❌ Shopify {$event->action()->getName()} failed: {$event->error()->getMessage()}"
        );
    }
}
```

### Usage

```php
// src/Command/SyncShopifyProductsCommand.php
class SyncShopifyProductsCommand extends Command
{
    public function __construct(
        private ShopifyIntegrationService $shopify,
    ) {}

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $products = $this->fetchProducts();

        foreach ($products as $id) {
            try {
                $this->shopify->syncProduct($id);
                // ✓ Logging + metrics + alerts all automatic
            } catch (\Exception $e) {
                // ✓ Sentry already captured via ActionFailed
                $output->writeln("Failed: $id");
            }
        }

        return self::SUCCESS;
    }
}
```

### What You Get

**Logs:**
```
[2026-09-18 14:23:45] INFO: Integration action completed
  integration: "shopify"
  action: "GetProduct"
  duration_ms: 145
  response_type: "App\Shopify\Response\ProductResponse"

[2026-09-18 14:25:12] WARNING: Slow integration request detected
  integration: "shopify"
  action: "GetInventory"
  duration_ms: 5234
  threshold_ms: 3000
  overage_ms: 2234
```

**Prometheus metrics:**
```
# HELP shopify_api_duration_ms Shopify API duration
# TYPE shopify_api_duration_ms histogram
shopify_api_duration_ms_bucket{action="GetProduct", status="success", le="100"} 45
shopify_api_duration_ms_bucket{action="GetProduct", status="success", le="500"} 312
shopify_api_duration_ms_bucket{action="GetInventory", status="success", le="5000"} 8
shopify_api_duration_ms_bucket{action="GetInventory", status="success", le="+Inf"} 10
```

**Sentry Errors:**
```
shopify.401_unauthorized
  integration: shopify
  action: GetOrders
  duration_ms: 234
  
  → 10 errors in 30 min
  → Actionable: token expired or revoked
```

**Slack Alerts:**
```
🐢 Shopify GetInventory was slow: 5234ms
❌ Shopify GetOrders failed: 401 Unauthorized
```

---

## Configuration Options

```php
ObservabilitySetup::register($dispatcher, $logger, [
    // Logging
    'logging' => true,                        // Enable automatic logging
    'log_level' => 'info',                    // PSR-3 level: debug, info, warning, error
    
    // Slow request alerts
    'slow_request_threshold_ms' => 5000,      // Alert if slower than this
    'slow_request_logger' => $logger,         // Logger for alerts (default: same logger)
    
    // Custom metrics
    'metrics_callback' => function($event) {
        // Record custom metrics (Prometheus, etc.)
        // $event is ActionCompleted or ActionFailed
    },
    
    // Custom error handling
    'error_callback' => function(ActionFailed $event) {
        // Send to Sentry, PagerDuty, etc.
    },
    
    // Filter to specific integration
    'integration_filter' => 'shopify',        // Only observe this integration
]);
```

---

---

## Performance

Observability has minimal overhead when configured correctly.

### Benchmark

| Setup | Cost per call |
|-------|--------------|
| No observability | 0ms (baseline) |
| Async logging + metrics | +0.06ms |
| Sync logging + metrics | +2-5ms ⚠️ |
| Metrics only | +0.01ms |
| Disabled | 0ms |

### Configuration for Production

#### Option 1: Async Logging (Recommended)

```yaml
# config/packages/monolog.yaml
monolog:
  handlers:
    main:
      type: buffer
      handler: stream
      buffer_size: 100      # Batch 100 logs before writing
      level: info          # Skip DEBUG in production
```

**Cost:** ~0.06ms per call (negligible)

```php
ObservabilitySetup::register($dispatcher, $logger, [
    'logging' => true,  # Writes to buffered logger (async)
    'slow_request_threshold_ms' => 3000,
    'metrics_callback' => fn($e) => $this->prometheus->record($e),
]);
```

#### Option 2: Metrics Only (Fastest)

If you only care about performance data, not logs:

```php
ObservabilitySetup::register($dispatcher, $logger, [
    'logging' => false,  # No logging overhead
    'slow_request_threshold_ms' => 3000,  # Alert only if slow
    'metrics_callback' => fn($e) => $this->prometheus->record($e),
]);
```

**Cost:** ~0.01ms per call (in-memory metric recording)

#### Option 3: Sampling (Hybrid)

Log only a percentage of requests:

```php
ObservabilitySetup::register($dispatcher, $logger, [
    'logging' => true,
    'slow_request_threshold_ms' => 3000,
    'integration_filter' => 'shopify',
    'metrics_callback' => function($e) {
        // Always record metrics (cheap)
        $this->prometheus->record($e);
        
        // Log only 10% of successful requests
        if ($e instanceof ActionCompleted && random_int(1, 100) <= 10) {
            $this->logger->info('Sampled log', [
                'action' => $e->action()->getName(),
                'duration_ms' => $e->durationMs(),
            ]);
        }
    },
]);
```

**Cost:** ~0.02ms per call (mostly metrics)

#### Option 4: Disabled in Tests

```php
// Don't create the dispatcher in test environment
$dispatcher = $env === 'test' ? null : new LifecycleEventDispatcher();

$engine = new IntegrationEngine(
    config: $config,
    client: $client,
    cache: $cache,
    integrationName: 'shopify',
    eventDispatcher: $dispatcher,  // null = zero overhead
);
```

**Cost:** 0ms (null checks are free)

---

## Quick Start with Generator Command

Instead of manually creating `ShopifyObservabilitySetup`, use the generator:

```bash
php bin/console make:observability shopify
```

This generates:
- `src/Integration/Shopify/ShopifyObservabilitySetup.php` (with stubs)
- Auto-registers in `services.yaml`

Then customize the callbacks for your needs.

---

## Performance Summary

| Scenario | Code | Cost |
|----------|------|------|
| **Development** | Full logging + metrics | +0.5ms (who cares) |
| **Production (Recommended)** | Async logging + metrics | +0.06ms |
| **Production (Max Performance)** | Metrics only | +0.01ms |
| **Tests** | Disabled (null dispatcher) | 0ms |

Pick Option 1 (async logging) for Shopify/POF. The overhead is unmeasurable at scale.

---

## Without Observability Setup (Manual)

```php
$dispatcher->subscribe(ActionCompleted::class, function(ActionCompleted $e) {
    $logger->info('Done', ['action' => $e->action()->getName()]);
});

$dispatcher->subscribe(ActionFailed::class, function(ActionFailed $e) {
    \Sentry\captureException($e->error());
});

// More boilerplate per integration...
```

## With Observability Setup (One call)

```php
ObservabilitySetup::register($dispatcher, $logger, [
    'logging' => true,
    'slow_request_threshold_ms' => 3000,
    'metrics_callback' => fn($e) => $prometheus->record($e),
    'error_callback' => fn($e) => Sentry\captureException($e->error()),
    'integration_filter' => 'shopify',
]);

// ✓ Logging + metrics + errors all wired up
// ✓ Async by default (no performance penalty)
// ✓ Customize or disable as needed
```

---

## Detailed Timing: HTTP vs. Mapping

When you need to identify performance bottlenecks precisely, subscribe to intermediate timing events.

### The Events

**ActionStarted** → (HTTP call) → **HttpResponseReceived** → (DTO mapping) → **ResponseMapped** → (final checks) → **ActionCompleted**

- **ActionStarted:** before anything (baseline t=0)
- **HttpResponseReceived:** after raw HTTP response arrives
  - `statusCode()` — HTTP status (`0` when the client doesn't report one: a custom `ClientInterface` or a response short-circuited by a middleware)
  - `durationMs()` — time spent in network + API processing
- **ResponseMapped:** after DTO mapping is complete
  - `httpDurationMs()` — same as HttpResponseReceived duration
  - `mappingDurationMs()` — time spent transforming response to DTO
  - `totalDurationMs()` — time since ActionStarted

### Example: Tracking Bottlenecks

```php
#[AsEventListener(event: ResponseMapped::class)]
public function onResponseMapped(ResponseMapped $event): void
{
    $httpTime = $event->httpDurationMs();       // External API latency
    $mappingTime = $event->mappingDurationMs();  // Transformation cost
    $overhead = $event->totalDurationMs() - $httpTime - $mappingTime;
    
    // Track each separately
    $this->prometheus->gauge('shopify.http_ms', $httpTime);
    $this->prometheus->gauge('shopify.mapping_ms', $mappingTime);
    $this->prometheus->gauge('shopify.overhead_ms', $overhead);
    
    // Or alert on specific bottlenecks
    if ($mappingTime > 100) {
        $this->logger->warning('Slow DTO mapping', [
            'action' => $event->action()->getName(),
            'mapping_ms' => $mappingTime,
        ]);
    }
}
```

### Use Cases

| Metric | Slow When | Action |
|--------|-----------|--------|
| `http_ms` high | External API is slow | Check API provider, optimize query |
| `mapping_ms` high | Transformation is expensive | Cache parsed results, profile mapper |
| `overhead_ms` high | Auth/config/middleware is expensive | Optimize middleware chain, cache auth tokens |

### Note

Intermediate timing events are only fired for **direct HTTP calls** (non-dynamic-auth requests where we control the full flow). When using dynamic authentication, the token fetch overhead is included in `ActionCompleted::durationMs()` but not broken down.

---

## Next Steps

1. **Generate** observability setup: `php bin/console make:observability shopify`
2. **Configure** async logging in `monolog.yaml`
3. **Customize** error/metrics callbacks
4. **Monitor** logs, Prometheus, Sentry
5. **Alert** on slow requests via Slack

No code changes needed in your integration service — events fire automatically.
