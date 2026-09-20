# 0011 · Messenger Support for Webhooks (Supersedes 0008)

- **Status:** Accepted
- **Date:** 2026-09-18
- **Supersedes:** [0008 · No Messenger bridge in the bundle](./0008-no-messenger-bridge-in-the-bundle.md)

> **Superseded in part by [ADR 0014](./0014-no-vendor-integrations-in-the-bundle.md).** Reversed in 6.0 ([ADR 0014](./0014-no-vendor-integrations-in-the-bundle.md)): the Messenger message and handler were removed, which restores [ADR 0008](./0008-no-messenger-bridge-in-the-bundle.md). Symfony hands webhooks to Messenger on its own through ConsumeRemoteEventMessage.

## Context

ADR 0008 decided that the bundle does not include Messenger integration. However, experience with v5.0 and v5.1 webhook implementations shows that:

1. Most webhook consumers use Symfony Messenger for async processing
2. The bundle can provide **optional message classes** without requiring Messenger as a dependency
3. This allows apps to use webhooks without Messenger (if they choose) while making it trivial for apps that do use Messenger

The tension: ADR 0008's intent was correct (bundle shouldn't mandate a queue system), but the implementation was too strict.

## Decision

**The bundle provides optional Messenger message classes; it remains transport-agnostic.**

Specifically:

1. **`ProcessWebhookMessage`** — An optional message class that apps can dispatch:
   ```php
   $this->messageBus->dispatch(new ProcessWebhookMessage($webhookPayload));
   ```

2. **`ProcessWebhookHandler`** — An optional handler template that apps can implement or extend

3. **Messenger is an optional dependency** — The bundle does not require it; apps that don't use Messenger are unaffected

4. **HTTP endpoint remains transport-agnostic:**
   ```php
   // Option A: Handle immediately
   $this->service->processWebhook($payload);
   
   // Option B: Queue with Messenger
   $this->messageBus->dispatch(new ProcessWebhookMessage($payload));
   
   // Option C: Custom queue (Redis, RabbitMQ, etc.)
   $this->customQueue->enqueue($payload);
   ```

## Reconciliation with ADR 0008

ADR 0008's **principle** stands: the bundle is not orchestration layer; it's a parsing and verification layer.

What changed:

- **v5.0/5.1 insight:** Providing optional message classes doesn't violate this principle; it's DX sugar for the common case (Messenger)
- **The bundle still doesn't mandate:** Apps can use webhooks without Messenger, without Messenger, without any queue

The bundle's stance is now:
> "The bundle parses webhooks and passes them to your app. If you use Messenger, here are convenient message classes. If you don't, ignore them."

This is no different than the bundle providing a REST adapter but not mandating REST over GraphQL.

## Alternatives considered

1. **Remove Messenger support entirely** (stick with ADR 0008)
   - Pros: Simpler bundle
   - Cons: Every app using Messenger has to write `ProcessWebhookMessage` themselves
   - Rejected: Adds boilerplate with no benefit

2. **Make Messenger a hard dependency**
   - Pros: Simpler for apps using Messenger
   - Cons: Violates ADR 0008's principle; breaks apps not using Messenger
   - Rejected: Unacceptable

## Consequences

**Positive:**
- Apps using Messenger have instant async webhook support with no boilerplate
- Bundle remains transport-agnostic; apps can use any queue
- Follows the principle of "convention over configuration for the common case"
- Backward compatible: apps that don't use Messenger are unaffected

**Negative:**
- Requires clear documentation that Messenger support is optional
- Adds complexity in the codebase (message classes, handler templates)

## Implementation Details

The bundle includes:

```php
// In src/Webhook/Infrastructure/Messenger/ProcessWebhookMessage.php
final class ProcessWebhookMessage
{
    public function __construct(public readonly string $platform, public readonly array $payload) {}
}

// In src/Webhook/Infrastructure/Messenger/ProcessWebhookHandler.php
#[AsMessageHandler]
final class ProcessWebhookHandler
{
    public function __invoke(ProcessWebhookMessage $message): void
    {
        // App-specific implementation
    }
}
```

Apps wire this in their own code:

```php
// In src/Webhook/ProcessWebhookHandler.php
#[AsMessageHandler]
class ProcessWebhookHandler
{
    public function __invoke(ProcessWebhookMessage $message): void
    {
        // Your business logic
        $this->orderService->process($message->payload);
    }
}
```

## References

- [ADR 0008 · No Messenger bridge in the bundle](./0008-no-messenger-bridge-in-the-bundle.md) — Superseded principle (still valid; implementation evolved)
- [WEBHOOK.md](../../WEBHOOK.md) — Async webhook processing guide
- [OBSERVABILITY.md](../../OBSERVABILITY.md) — Event-driven observability
