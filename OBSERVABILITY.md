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
```

---

## Next Steps

1. **Configure** in `services.yaml` (see example above)
2. **Pass dispatcher** to engine via DI
3. **Customize** error and metrics callbacks for your needs
4. **Watch** logs, Prometheus, Sentry to see integration health

No code changes needed in your integration service — events fire automatically.
