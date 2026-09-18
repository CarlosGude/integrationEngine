# Upgrading from v4.x to v5.0

This guide covers the features introduced in v5.0 and how to adopt them.

## Overview of v5.0

v5.0 introduces **inbound webhook support** for receiving events from external providers (Stripe, PayPal, Shopify, etc.). The feature is entirely **opt-in** — existing outbound integrations are not affected.

## What's New

### 1. Inbound Webhook Framework

v5.0 adds first-class support for receiving webhooks with signature verification and idempotency.

**New components:**
- `SignatureVerifierInterface` — Verify webhook signatures (HMAC-SHA256, timestamped, etc.)
- `WebhookEventInterface` — Marker interface for typed webhook event DTOs
- `AbstractWebhookMapper` — Transform raw webhook payloads to typed events
- `IntegrationWebhookRequestParser` — Handle incoming webhook requests (extends Symfony's `AbstractRequestParser`)
- `make:webhook` command — Generate webhook scaffolding

### 2. Webhook Configuration (New)

Webhooks are configured in your integration's YAML file:

```yaml
integrations:
    stripe:
        base_url: 'https://api.stripe.com'
        config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/Stripe/Stripe.yaml'
        webhooks:
            charge.succeeded:
                mapper: 'App\Infrastructure\Webhooks\Stripe\ChargeSucceededMapper'
                signature:
                    type: timestamped_hmac
                    header: Stripe-Signature
```

## Migration Path: Receiving Your First Webhook

If you're **new to webhooks**, follow this path:

### Step 1: Generate webhook scaffold

```bash
php bin/console make:webhook stripe charge.succeeded
```

### Step 2: Define the event DTO

```php
namespace App\Infrastructure\Webhooks\Stripe;

use IntegrationEngine\Webhook\Contract\WebhookEventInterface;

final readonly class ChargeSucceededEvent implements WebhookEventInterface
{
    public function __construct(
        public string $chargeId,
        public int $amount,
        public string $currency,
    ) {}

    public static function create(array $payload): self
    {
        $data = $payload['data']['object'] ?? [];
        return new self(
            chargeId: $data['id'],
            amount: (int)$data['amount'],
            currency: $data['currency'],
        );
    }
}
```

### Step 3: Implement the webhook mapper

```php
namespace App\Infrastructure\Webhooks\Stripe;

use IntegrationEngine\Webhook\Contract\AbstractWebhookMapper;
use IntegrationEngine\Webhook\Contract\WebhookEventInterface;

final class ChargeSucceededMapper extends AbstractWebhookMapper
{
    public function map(array $payload, array $headers): WebhookEventInterface
    {
        return ChargeSucceededEvent::create($payload);
    }
}
```

### Step 4: Wire into your integration config

```yaml
webhooks:
    charge.succeeded:
        mapper: 'App\Infrastructure\Webhooks\Stripe\ChargeSucceededMapper'
        signature:
            type: timestamped_hmac
            header: Stripe-Signature
```

### Step 5: Listen to the event in your application

```php
class StripeWebhookListener
{
    #[AsEventListener(event: ChargeSucceededEvent::class)]
    public function onChargeSucceeded(ChargeSucceededEvent $event): void
    {
        // Handle the event — update domain, trigger workflows, etc.
        $this->chargeService->recordCharge($event->chargeId, $event->amount);
    }
}
```

### Step 6: Expose the webhook endpoint

```php
#[Route('/webhooks/stripe', name: 'webhook_stripe', methods: ['POST'])]
public function receiveStripe(IntegrationWebhookRequestParser $parser, Request $request): Response
{
    try {
        $remoteEvent = $parser->parse($request, 'stripe');
        // Event is dispatched automatically; return 202 Accepted
        return new Response('', Response::HTTP_ACCEPTED);
    } catch (WebhookValidationException $e) {
        return new Response('', Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
```

## Signature Verification Strategies

v5.0 supports two built-in strategies:

### Simple HMAC-SHA256

For providers like WooCommerce that use basic HMAC:

```yaml
signature:
    type: hmac
    header: X-WC-Webhook-Signature
```

### Timestamped HMAC (Stripe model)

For providers that include a timestamp in the signature header:

```yaml
signature:
    type: timestamped_hmac
    header: Stripe-Signature
```

For custom signature schemes, implement `SignatureVerifierInterface`.

## Architecture Decision Records

See `docs/adr/0009-inbound-webhooks.md` for design rationale and boundaries.

## Breaking Changes

**None.** v5.0 is purely additive. Outbound integrations work unchanged.

## Migration Checklist

If you're adding webhooks to an existing integration:

- [ ] Install the bundle and update `composer.json` to `^5.0`
- [ ] Create webhook event DTOs implementing `WebhookEventInterface`
- [ ] Create webhook mappers extending `AbstractWebhookMapper`
- [ ] Add webhook configuration to your integration's YAML
- [ ] Create the webhook endpoint in your controller
- [ ] Listen to domain events in your application services
- [ ] Test with `make:webhook` scaffolding or manual payload testing
- [ ] Review `WEBHOOK.md` for platform-specific guides (Stripe, PayPal, etc.)

## Compatibility

- ✅ PHP 8.2+
- ✅ Symfony 6.4+, 7.x, 8.x
- ✅ All existing v4.x integrations work unchanged

## Support

For questions or issues during upgrade:

- Complete guide: [`WEBHOOK.md`](./WEBHOOK.md)
- Architecture: [`docs/adr/0009-inbound-webhooks.md`](./docs/adr/0009-inbound-webhooks.md)
- GitHub discussions: [IntegrationEngine discussions](https://github.com/carlosgude/integrationengine/discussions)
