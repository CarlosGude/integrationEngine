# Inbound webhooks in v8

The bundle verifies the raw HTTP body, decodes authenticated JSON, and maps it to an immutable application event. Your application owns delivery, persistence and deduplication.

Install optional components with `composer require symfony/webhook symfony/remote-event symfony/messenger`. The parser supports Symfony 6.4, 7.4 and 8.x. Messenger is needed by the standard Symfony controller, including synchronous consumers.

## Integration configuration

```yaml
webhooks:
    type_field: type
    id_field: id
    signature:
        type: timestamped_hmac
        header: Stripe-Signature
        secret: '%env(STRIPE_WEBHOOK_SECRET)%'
        tolerance: 300
    unknown_events: reject
    events:
        payment_intent.succeeded:
            mapper: App\Webhook\PaymentIntentSucceededMapper
```

Use dot paths such as `data.object.id` for nested fields. Event types must be strings and IDs must be strings or integers. A configured mapper must extend `AbstractWebhookMapper`, and its `eventType()` must match its YAML key. The root `webhooks` key is reserved and never becomes an outgoing action.

Other signature schemes:

```yaml
signature:
    type: hmac_sha256
    header: X-Signature
    secret: '%env(PROVIDER_WEBHOOK_SECRET)%'
    prefix: 'sha256=' # omit for an unprefixed hexadecimal digest
```

`hmac_base64` verifies the base64-encoded binary HMAC-SHA256 digest without prefix. Only `timestamped_hmac` accepts `tolerance`; only `hmac_sha256` accepts `prefix`. Empty secrets are rejected. `%env()%` resolution belongs to the container, not the Core parser.

## Typed mapping

```php
use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;

final readonly class PaymentIntentSucceeded implements WebhookEventInterface
{
    public function __construct(public string $paymentId) {}
}

final class PaymentIntentSucceededMapper extends AbstractWebhookMapper
{
    public static function eventType(): string
    {
        return 'payment_intent.succeeded';
    }

    protected static function transform(array $payload, array $headers): PaymentIntentSucceeded
    {
        $id = $payload['data']['object']['id'] ?? null;
        if (!is_string($id)) {
            throw new UnexpectedValueException('Missing payment ID.');
        }

        return new PaymentIntentSucceeded($id);
    }
}
```

Mapper failures are application defects or domain validation failures; they are not disguised as signature errors. The final `map()` method enforces the event-type invariant. Event DTOs should contain only scalars, arrays and other readonly serializable objects.

## Explicit Symfony routing

```yaml
framework:
    webhook:
        routing:
            stripe:
                service: integration_engine.webhook_parser.stripe
                secret: ''
```

**The integration YAML secret is authoritative.** The Symfony routing secret is intentionally unused; leave it empty to avoid two independent configuration sources.

On Symfony 6.4/7.4, import `@FrameworkBundle/Resources/config/routing/webhook.xml`; on Symfony 8.x import `@FrameworkBundle/Resources/config/routing/webhook.php`:

```yaml
webhook:
    resource: '@FrameworkBundle/Resources/config/routing/webhook.xml'
    prefix: /webhook
```

The standard controller responds 202 to accepted known events and 406 to a null parse result. Therefore use `unknown_events: reject` with that controller. The parser's `ignore` policy returns null **only after successful authentication and payload validation**. A custom application endpoint can acknowledge that result with 202 without dispatching a message. See the [version-specific spike](spikes/webhooks-v8.md) for the transport decision still under review.

## Application consumer and idempotency

```php
use IntegrationEngine\Infrastructure\Webhook\MappedRemoteEvent;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;

#[AsRemoteEventConsumer('stripe')]
final readonly class StripeConsumer implements ConsumerInterface
{
    public function consume(RemoteEvent $remoteEvent): void
    {
        if (!$remoteEvent instanceof MappedRemoteEvent) {
            throw new UnexpectedValueException('Expected a mapped webhook event.');
        }
        $event = $remoteEvent->event();
        // In one database transaction: insert a receipt with a unique
        // (provider, remote event ID), then apply domain side effects.
        // A duplicate receipt means the work was already committed.
    }
}
```

Delivery is at least once. Store the provider and `getId()` under a unique constraint in the **same transaction** as side effects. For external side effects, use an outbox and the provider's idempotency key. The bundle has no cache-based deduplicator or exactly-once guarantee. Multiple workers and retries remain application concerns.

## Rejection reasons and observations

All verifier/parser rejections expose `RejectWebhookException` with HTTP 406, message `Webhook rejected: <reason>`, and a previous `WebhookRejectedException` exposing `reason()`.

| Reason | Meaning |
| --- | --- |
| `header_missing` | Signature header absent |
| `header_malformed` | Duplicate/empty header, bad prefix or timestamp syntax |
| `signature_invalid` | No expected signature matches the exact raw body |
| `timestamp_out_of_tolerance` | Authenticated timestamp outside the inclusive window |
| `payload_invalid` | Unsupported method/media type, invalid JSON or missing type/ID |
| `unknown_event` | No configured mapper and reject policy selected |

Neither exception message includes secrets, signatures or body content. Optional PSR events contain only integration, event type/ID or rejection reason, and timestamp. `MappedRemoteEvent` deliberately retains the raw decoded payload for application consumers; it must not be logged wholesale.

Timestamped HMAC accepts any matching `v1`, ignores `v0`, signs `timestamp.rawBody`, and accepts both exact tolerance boundaries using an injected PSR clock. Multiple `v1` values support provider key rotation; the application must still deploy the matching configured secret. Raw whitespace/key order are significant; never re-encode JSON before verification.

## Generation and tests

```bash
php bin/console make:webhook Stripe payment_intent.succeeded --signature-type=timestamped_hmac --signature-header=Stripe-Signature --no-interaction
```

The command writes a readonly DTO, a static mapper, and merges the integration YAML. Existing PHP files and event mappings are preserved unless `--force` is supplied. The bundle supplies the generic parser; no generated parser subclass is needed.

In tests sign the exact body with `hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret)` and send `t=<timestamp>,v1=<digest>`. Use a fixed `Psr\Clock\ClockInterface` in timestamp tests, with no sleeps. Test your mapper independently with `YourMapper::map(YourMapper::eventType(), $payload, $headers)`.

## Migration from v7

Replace parser subclasses with YAML event mappings; move instance mapper `getDefinition()`/`map()` to static `eventType()`/protected `transform()`. Verifiers now accept headers and `SignatureConfig`, return void, and throw typed reasons. Replace the legacy per-event YAML list with the root definition above. Remove bundle deduplication helpers in favor of durable application receipts. Consumers read `MappedRemoteEvent::event()` rather than mapping payloads a second time.
