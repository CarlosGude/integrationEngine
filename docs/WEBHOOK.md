# Webhook Integration Guide

Inbound webhooks in v8 are configured in the same integration YAML as outbound actions, but they use Symfony's Webhook/RemoteEvent transport. The bundle supplies provider-neutral signature schemes, parsing and typed mapping; the application owns business handling and idempotency.

## Requirements

Webhook support is optional. A consuming application that enables a `webhooks:` definition needs Symfony's webhook/remote-event stack available; the bundle detects `AbstractRequestParser` during container compilation and rejects webhook wiring when the component is missing.

For Symfony's standard webhook controller/consumer path install Messenger as well:

```bash
composer require symfony/webhook symfony/remote-event symfony/messenger
```

Messenger is a requirement of Symfony's supplied webhook controller flow, not of the parser itself. An application that invokes the parser through its own controller/transport can choose a different delivery mechanism.

The generated parser service ID is:

```text
integration_engine.webhook_parser.<integration-name>
```

## 1. Define the webhook contract in integration YAML

```yaml
# src/Infrastructure/Integrations/Stripe/Stripe.yaml
webhooks:
    type_field: type
    id_field: id
    unknown_events: reject
    signature:
        type: hmac_sha256
        header: X-Webhook-Signature
        secret: '%env(STRIPE_WEBHOOK_SECRET)%'
    events:
        payment.completed:
            mapper: App\Webhooks\Stripe\PaymentCompletedMapper
```

The top-level action entries and `webhooks:` block coexist in the same file.

### Fields

| Field | Meaning |
|---|---|
| `type_field` | dot path used to read the provider event type |
| `id_field` | dot path used to read the provider event ID |
| `unknown_events` | `reject` or `ignore` |
| `signature.type` | `hmac_sha256`, `hmac_base64`, or `timestamped_hmac` |
| `signature.header` | header containing the provider signature |
| `signature.secret` | signing secret; may be an env placeholder resolved by DI |
| `signature.tolerance` | required only for `timestamped_hmac` |
| `signature.prefix` | optional only for `hmac_sha256` |
| `events.<type>.mapper` | mapper class; its `eventType()` must equal the YAML event key |

`type_field` and `id_field` are non-empty dot-separated key paths. Event IDs may be strings or integers and are normalized to strings.

## 2. Create a typed event and mapper

```php
use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;

final readonly class PaymentCompleted implements WebhookEventInterface
{
    public function __construct(
        public string $id,
        public int $amount,
    ) {}
}

final class PaymentCompletedMapper extends AbstractWebhookMapper
{
    public static function eventType(): string
    {
        return 'payment.completed';
    }

    protected static function transform(array $payload, array $headers): WebhookEventInterface
    {
        return new PaymentCompleted(
            id: (string) $payload['id'],
            amount: (int) $payload['data']['amount'],
        );
    }
}
```

`AbstractWebhookMapper::map()` checks that the runtime event type equals `eventType()` before calling `transform()`.

## 3. Route Symfony Webhook to the generated parser

Configure `framework.webhook.routing` with the parser service. The parser deliberately ignores Symfony's routing secret and uses the secret from the integration YAML as the single signing-secret source, so leave the routing secret empty.

```yaml
# config/packages/framework.yaml
framework:
    webhook:
        routing:
            stripe:
                service: 'integration_engine.webhook_parser.stripe'
                secret: ''
```

Import Symfony's webhook route resource according to the Symfony version used by the consuming application. Symfony 6.4/7.x and 8.x use different resource formats; follow that version's Webhook documentation rather than copying a route file across majors.

## 4. Consume the `RemoteEvent`

The parser returns `MappedRemoteEvent`, a `RemoteEvent` that retains both the authenticated decoded payload and the already-mapped typed event:

```php
use IntegrationEngine\Infrastructure\Webhook\MappedRemoteEvent;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;

#[AsRemoteEventConsumer('stripe')]
final class StripeWebhookConsumer implements ConsumerInterface
{
    public function consume(RemoteEvent $event): void
    {
        if (!$event instanceof MappedRemoteEvent) {
            return;
        }

        $typedEvent = $event->event();
        // hand $typedEvent to application/domain code
    }
}
```

This is the shortest v8 path. `WebhookEventDispatcher` and `ConsumesWebhookEvents` remain available for applications that want to map/dispatch from a plain `RemoteEvent`, but the v8 parser already returns a `MappedRemoteEvent`; do not remap it just to reach the same DTO again.

## Parser behavior

The current parser:

1. accepts only `POST`;
2. accepts `application/json` and structured `application/*+json` media types;
3. reads the raw request bytes;
4. verifies the configured signature **before** JSON decoding;
5. requires a JSON object at the root (lists/scalars are rejected);
6. reads event type/id using configured dot paths;
7. validates the event against the configured mapper table;
8. returns `MappedRemoteEvent` and emits `WebhookReceived`.

Rejected requests use HTTP 406 through Symfony's `RejectWebhookException` and emit `WebhookRejected`. Rejection messages carry fixed reason codes and do not include payloads, signatures or secrets.

## Unknown events

`unknown_events: reject` explicitly rejects an authenticated but undeclared event.

`unknown_events: ignore` makes the parser return `null`. Be aware that Symfony Webhook controller behavior around a `null` parser result is version-sensitive and, in the supported 6.4/7.4/8.0 controller flow verified by the project spike, a null event is not a portable way to guarantee a 2xx acknowledgement. Use `reject` when you need unambiguous behavior, or own the acknowledgement/controller policy in the application.

See [webhooks-v8.md](./webhooks-v8.md) for the compatibility finding behind this caveat.

## Signature schemes

### `hmac_sha256`

Compares a hexadecimal HMAC-SHA256 signature. Optional `prefix` is supported by this scheme only.

### `hmac_base64`

Compares the raw HMAC-SHA256 digest encoded as base64.

### `timestamped_hmac`

Validates timestamped HMAC input and requires a non-negative `tolerance`. It uses a PSR-20 clock through the bundle's `SystemClock` adapter.

## Generator

```bash
php bin/console make:webhook stripe payment.completed \
    --signature-type=hmac_sha256 \
    --signature-header=X-Webhook-Signature
```

The command generates a typed event and mapper, and merges the webhook definition into the integration YAML. It then prints the parser service ID to use in Symfony routing.

## Idempotency belongs to the application

v8 does **not** ship `WebhookIdempotencyService`, `WebhookFingerprinter` or an idempotency storage port. A provider can redeliver the same valid event, so side-effecting consumers must still be idempotent.

Prefer a durable unique receipt keyed by provider/event ID and, where possible, write that receipt in the same transaction as the business side effect. A cache TTL alone cannot guarantee exactly-once processing.

## Business boundary

Signature verification proves authenticity according to the configured scheme. It does not make payload fields trustworthy domain state. Keep validation and business invariants in the consuming application after mapping.

## Related documents

- [webhooks-v8.md](./webhooks-v8.md) — v8 migration/compatibility notes
- [LIFECYCLE.md](./LIFECYCLE.md) — `WebhookReceived` / `WebhookRejected`
- [ADR 0016](./adr/0016-generic-webhooks-and-application-idempotency.md) — generic webhook + application idempotency decision
- [UPGRADE-8.0.md](./UPGRADE-8.0.md) — migration from the v7 webhook model
