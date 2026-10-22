# Webhook Integration Guide

Inbound webhooks in v8 are configured in the same integration YAML as outbound actions, but they use Symfony's Webhook/RemoteEvent transport. The bundle supplies provider-neutral signature schemes, parsing and typed mapping; the application owns business handling and idempotency.

## Request flow

A webhook request crosses three layers, only the first of which belongs to this bundle. Knowing where one layer ends and the next begins is the key to reading everything below:

```
External provider (e.g. Stripe)
  → POST https://your-app/webhook/<name>                              [HTTP request — synchronous]

  → Symfony\Webhook\Controller\WebhookController::handle()             (symfony/webhook — NOT this bundle)
      resolves the parser configured under framework.webhook.routing.<name>
      → IntegrationWebhookRequestParser::parse($request, $secret)      (THIS BUNDLE)
          → validate()      method + content-type check; reject() on mismatch
          → doParse()
              → SignatureVerifierInterface::verify()   on raw bytes, BEFORE decoding
              → json_decode()                           reject() on invalid/non-object JSON
              → read type/id via type_field/id_field dot paths
              → WebhookDefinition::mapperFor(type)
                    no mapper, unknown_events: reject  → reject() → RejectWebhookException, emits WebhookRejected
                    no mapper, unknown_events: ignore  → return null (see "Unknown events" below — NOT a success)
              → Mapper::map() → transform() → WebhookEventInterface DTO
              → wrap in MappedRemoteEvent (a RemoteEvent carrying the typed DTO)
              → emit WebhookReceived
  ← reject() was called       → 406 Rejected (fixed reason code, WebhookRejected emitted); Messenger never involved
  ← parser returned null      → 406 Rejected (generic message, no reason code, no WebhookRejected — same status
                                 as a rejection even though the parser itself never called reject())
  ← parser returned a MappedRemoteEvent
      → WebhookController dispatches it through Messenger: $bus->dispatch(new ConsumeRemoteEventMessage(...))
          default synchronous bus → Messenger runs your consumer (below) right here, inside the HTTP request
          async transport         → Messenger only enqueues the message; a worker runs your consumer later
      → WebhookController responds 202 Accepted
                                                                               [HTTP REQUEST ENDS HERE, any branch]

  → your #[AsRemoteEventConsumer('<name>')] class::consume(RemoteEvent $event)   (YOUR APPLICATION)
      → $event instanceof MappedRemoteEvent
      → $typedEvent = $event->event()
      → hand $typedEvent to application/domain code
```

A rejection (406) goes back to the provider without Messenger or your consumer ever being involved. An accepted event is dispatched to Messenger *before* the 202 is sent. With the default synchronous bus your `#[AsRemoteEventConsumer]` consumer therefore runs inside the webhook request, before the provider gets its response; only when the app routes `ConsumeRemoteEventMessage` to an async transport does the 202 go out first and the consumer run later, on a worker. Steps 1 and 2 below (YAML contract, mapper) are what you write for the first layer. Steps 3 and 4 are, respectively, telling *Symfony* which parser owns a routing name, and writing the consumer that runs *after* Messenger hands the event back to your code — the bundle has no part in that handoff; it only produces the `MappedRemoteEvent` that Messenger carries.

### Why dispatch an event at all, instead of just calling your business logic?

Because the webhook connection needs to close fast, and your business logic is allowed to be slow or fail. Most providers (Stripe, GitHub, ...) expect an acknowledgement within a few seconds and will treat a slow or failed response as "not delivered" — retrying, and redelivering the same event. If verifying the signature and building the typed DTO were the *last* thing that happened before responding — i.e. if your domain code (database writes, calls to other services, emails, ...) ran inline, in the same request — then a slow dependency or a thrown exception in *your* code would make the provider think the webhook itself failed, and it would resend an event you may have already partially processed.

Dispatching through Messenger is what *lets* the controller respond 202 the moment the event is authenticated and mapped, without waiting on business logic — but only if the app actually routes `ConsumeRemoteEventMessage` to an async transport. Without that routing, Messenger's default bus runs the consumer synchronously, inline, as part of `$bus->dispatch()` — which happens *before* `createSuccessfulResponse()` returns. See [What happens if the event fails](#what-happens-if-the-event-fails): that distinction is not cosmetic, it changes what the provider sees on failure.

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

This step is two separate classes with two separate jobs. Neither of them is Symfony's `RemoteEvent` — you never write that class yourself; the bundle builds it for you as `MappedRemoteEvent` once your mapper has run (see step 4). What you write here only produces the *typed payload* that ends up riding inside it.

**The event: the typed shape your domain code will actually receive.**

```php
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;

final readonly class PaymentCompleted implements WebhookEventInterface
{
    public function __construct(
        public string $id,
        public int $amount,
    ) {}
}
```

`WebhookEventInterface` is just a marker — this class has no behavior of its own. It exists so that, by the time your application code sees the webhook, it's working with `$event->amount` (an `int`, type-checked) instead of `$payload['data']['amount']` (an untyped array offset that could be a string, missing, or the wrong shape). One of these per event *type* you care about (`payment.completed`, `payment.refunded`, ...), each shaped for what that event actually carries.

**The mapper: the only place that knows how to build that shape from the raw payload.**

```php
use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;

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

This is the class the YAML `events.payment.completed.mapper` key (step 1) points at — it's the glue between "a declared event type" and "a typed DTO". `eventType()` is a self-check: `AbstractWebhookMapper::map()` compares it against the event type the parser just read from the payload, and throws if they don't match. That guards against the one way this can be misconfigured silently: copy-pasting a mapper for a new event type and forgetting to update `eventType()` — without the check, it would quietly build the wrong DTO for the wrong event instead of failing loudly.

You write one mapper per event type, same as one typed event class per event type. The parser (step 4's diagram) is what actually calls `Mapper::map()` for you — you never call it yourself.

## 3. Route Symfony Webhook to the generated parser

This is the `<name>` in the [Request flow](#request-flow) diagram above: it tells Symfony's own `WebhookController` which parser service handles requests to this webhook. Configure `framework.webhook.routing` with the parser service. The parser deliberately ignores Symfony's routing secret and uses the secret from the integration YAML as the single signing-secret source, so leave the routing secret empty.

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

This is the last hop in the [Request flow](#request-flow) diagram: the consumer below runs when Messenger delivers the event — inline, before the 202, on the default synchronous bus; after the HTTP response has already gone back to the provider when `ConsumeRemoteEventMessage` is routed to an async transport. The parser returns `MappedRemoteEvent`, a `RemoteEvent` that retains both the authenticated decoded payload and the already-mapped typed event:

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

See [webhook-compatibility-notes.md](./webhook-compatibility-notes.md) for the compatibility finding behind this caveat.

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

## What happens if the event fails

There are two different points where "the event fails", and the provider sees a completely different outcome depending on which one it is.

### Rejected before acceptance — inside the HTTP request

This is the parser (the `validate()`/`doParse()` branch in the [Request flow](#request-flow) diagram) refusing to hand the event onward at all: invalid signature, malformed/non-object JSON, missing type/id, or an undeclared type under `unknown_events: reject`. See [Parser behavior](#parser-behavior) and [Unknown events](#unknown-events) above for the exact checks. The HTTP response is 406, `WebhookRejected` is emitted with a fixed reason code, and no mapper, consumer, or Messenger dispatch ever runs — the event never got far enough to reach any of that. What the provider does next (retry, give up, alert a human) is entirely its own policy; the bundle has no say in it.

### Failing after acceptance — inside (or after) consumption

This is the case where authentication and mapping both succeeded, the event was handed to Messenger, and then your `#[AsRemoteEventConsumer]` code itself throws — bad data for your domain rules, a downstream dependency being down, a database constraint, anything. What the provider sees here depends entirely on whether `ConsumeRemoteEventMessage` is routed to an async transport in `messenger.yaml`:

- **No async routing (the default if you don't add one):** the consumer runs *inside* the HTTP request, before the response is sent. An exception there is wrapped in a `HandlerFailedException` and propagates straight out of `WebhookController::handle()` — Symfony turns that into an HTTP 500 to the provider. The provider sees a failed webhook and will retry/redeliver the same event, even though signature verification and mapping both succeeded cleanly the first time. This also means the "respond fast" design from [Why dispatch an event at all](#why-dispatch-an-event-at-all-instead-of-just-calling-your-business-logic) does not actually hold: a slow or failing consumer makes the *webhook request itself* slow or failing.
- **Routed to an async transport (the recommended setup):** `$bus->dispatch()` only enqueues the message and returns immediately; the 202 has already gone out regardless of what happens next. A worker later picks up the message and runs your consumer out-of-band. If it throws there, Messenger's own `retry_strategy` (`max_retries`, `delay`, `multiplier`) retries it automatically, on its own schedule — not the provider's. Once retries are exhausted, the message moves to the transport's `failure_transport` if one is configured (Symfony's built-in dead-letter queue — see [upgrade-6.0.md](./upgrade-6.0.md) for why the bundle doesn't ship its own DLQ on top of it), or is dropped if none is configured. Either way, **the provider never finds out** — it already got its 2xx and has no reason to redeliver.

That second bullet is exactly why idempotency matters even when everything above looks healthy: Messenger retrying your consumer means the same event can run through your business logic more than once (e.g. it half-succeeded, then failed, then retried), with no redelivery from the provider to tip you off. See the next section.

## Idempotency belongs to the application

v8 does **not** ship `WebhookIdempotencyService`, `WebhookFingerprinter` or an idempotency storage port. A provider can redeliver the same valid event, so side-effecting consumers must still be idempotent.

Prefer a durable unique receipt keyed by provider/event ID and, where possible, write that receipt in the same transaction as the business side effect. A cache TTL alone cannot guarantee exactly-once processing.

For example, a `webhook_receipts` table with a `UNIQUE(provider, event_id)` constraint, written in the same transaction as the side effect it's guarding:

```php
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use IntegrationEngine\Infrastructure\Webhook\MappedRemoteEvent;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;

#[AsRemoteEventConsumer('stripe')]
final class StripeWebhookConsumer implements ConsumerInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly OrderService $orders, // app's own service, not part of the bundle
    ) {}

    public function consume(RemoteEvent $event): void
    {
        if (!$event instanceof MappedRemoteEvent) {
            return;
        }

        $typedEvent = $event->event();
        if (!$typedEvent instanceof PaymentCompleted) {
            return;
        }

        $this->connection->transactional(function () use ($typedEvent): void {
            try {
                $this->connection->insert('webhook_receipts', [
                    'provider' => 'stripe',
                    'event_id' => $typedEvent->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                return; // already processed this event — skip the side effect, don't throw
            }

            $this->orders->markPaid($typedEvent->id, $typedEvent->amount);
        });
    }
}
```

The unique constraint is what makes this safe under retries, whether it's the provider redelivering or Messenger retrying a failed consumer: the insert and `markPaid()` commit together or not at all, so there's no window where the receipt exists but the side effect didn't run, or vice versa. A first delivery inserts the receipt and runs `markPaid()`. A redelivery or Messenger retry hits the unique constraint, catches it, and returns — `markPaid()` never runs a second time. An unrelated failure (e.g. `markPaid()` itself throwing) rolls back the whole transaction, receipt included, so that event is correctly still "unprocessed" and eligible to run again on the next retry.

## Business boundary

Signature verification proves authenticity according to the configured scheme. It does not make payload fields trustworthy domain state. Keep validation and business invariants in the consuming application after mapping.

## Related documents

- [webhook-compatibility-notes.md](./webhook-compatibility-notes.md) — contract background and compatibility notes
- [lifecycle.md](./lifecycle.md) — `WebhookReceived` / `WebhookRejected`
- [ADR 0016](./adr/0016-generic-webhooks-and-application-idempotency.md) — generic webhook + application idempotency decision
- [upgrade-8.0.md](./upgrade-8.0.md) — migration from the v7 webhook model
