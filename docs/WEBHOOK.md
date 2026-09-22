# Webhook Integration Guide

IntegrationEngine does not ship its own webhook endpoint. Inbound webhooks go through Symfony's Webhook component (`symfony/webhook` + `symfony/remote-event`), and the bundle provides the pieces that plug into it:

- **`IntegrationWebhookRequestParser`** — base request parser that verifies the signature and decodes the payload
- **Signature verifiers** — hex HMAC behind a prefix, raw HMAC in base64, timestamped HMAC; or your own `SignatureVerifierInterface`
- **`AbstractWebhookMapper`** — turns the raw payload into a typed `WebhookEventInterface` DTO
- **`WebhookEventDispatcher`** — maps a verified `RemoteEvent` and dispatches the typed event to your listeners

```bash
composer require symfony/webhook   # pulls symfony/remote-event and symfony/messenger
```

## How It Fits Together

```
POST /webhook/{type}                         Symfony's WebhookController (framework.webhook.routing)
  → YourParser::parse()                      extends IntegrationWebhookRequestParser
      verify signature (SignatureVerifierInterface)   → 406 on failure
      decode JSON object → RemoteEvent(name: getDefinition(), id: payload.id, payload)
  → Messenger (sync by default; async if you route ConsumeRemoteEventMessage to a transport)
  → YourConsumer::consume(RemoteEvent)       #[AsRemoteEventConsumer('{type}')]
      WebhookEventDispatcher::dispatch($event, $mapper, $headers)
        → AbstractWebhookMapper::map()  → typed WebhookEventInterface DTO
        → EventDispatcher::dispatch(DTO)
  → your #[AsEventListener] listeners        (application layer)
```

Symfony answers `202 Accepted` once the event has been handed to Messenger.

## Step by Step

The example is Stripe's `payment_intent.succeeded`. `php bin/console make:webhook stripe payment_intent.succeeded` asks for the verifier type (`hmac_sha256` for a hex digest behind a prefix, `hmac_base64` for the raw digest base64-encoded, `timestamped_hmac` for the signed-timestamp shape) and the signature header and scaffolds steps 1–3 and 5 under `src/Webhooks/Stripe/` (namespace `App\Webhooks\Stripe`; change with `--namespace` / `--path`). It then prints the routing entry for step 4, keyed `stripe_payment_intent_succeeded` — the same name the generated consumer answers to, and the URL segment the provider posts to.

Only step 6, the listener, is left: that one is your domain.

The generated parser has no constructor and needs no `services.yaml` entry: it is autowired as it stands, and the signing secret arrives from the routing entry.

### 1. Event DTO

```php
namespace App\Webhooks\Stripe;

use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;

final readonly class PaymentIntentSucceededEvent implements WebhookEventInterface
{
    public function __construct(
        public string $paymentIntentId,
        public int $amount,
        public string $currency,
    ) {}
}
```

### 2. Mapper

`getDefinition()` must return the same event type as the parser: `WebhookEventDispatcher` throws if they differ.

```php
namespace App\Webhooks\Stripe;

use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;

final class PaymentIntentSucceededEventMapper extends AbstractWebhookMapper
{
    public function getDefinition(): string
    {
        return 'payment_intent.succeeded';
    }

    public function map(array $payload, array $headers): PaymentIntentSucceededEvent
    {
        $intent = $payload['data']['object'];

        return new PaymentIntentSucceededEvent(
            paymentIntentId: (string) $intent['id'],
            amount: (int) $intent['amount'],
            currency: (string) $intent['currency'],
        );
    }
}
```

### 3. Parser

```php
namespace App\Webhooks\Stripe;

use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\SignatureVerifierInterface;
use IntegrationEngine\Core\Webhook\TimestampedHmacSignatureVerifier;
use IntegrationEngine\Infrastructure\Webhook\IntegrationWebhookRequestParser;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PaymentIntentSucceededRequestParser extends IntegrationWebhookRequestParser
{
    public function __construct(
        private readonly ClockInterface $clock,
        #[Autowire(env: 'STRIPE_WEBHOOK_SECRET')]
        private readonly string $secret,
    ) {}

    public function getDefinition(): string
    {
        return 'payment_intent.succeeded';
    }

    public function getMapper(): AbstractWebhookMapper
    {
        return new PaymentIntentSucceededEventMapper();
    }

    protected function getSignatureVerifier(): SignatureVerifierInterface
    {
        return new TimestampedHmacSignatureVerifier('Stripe-Signature', 300, $this->clock);
    }

    protected function getSignatureSecret(): string
    {
        return $this->secret;
    }
}
```

> The parser verifies with the secret Symfony passes in from `framework.webhook.routing.<type>.secret`, and falls back to `getSignatureSecret()` when that one is empty. If both are empty the request is rejected (`406`): an empty key would accept HMACs anyone can compute.

The parser class must not be `readonly`: Symfony's `AbstractRequestParser` isn't, and a readonly class can't extend a non-readonly one.

The base parser accepts POST requests and verifies the signature before decoding
the body. Malformed JSON and a root value that is not an object return `406`;
lists such as `[]` or `[{"id": 1}]` are rejected. Empty objects, objects with
numeric keys and nested lists are supported. `Content-Type` restrictions can be
added by overriding the request matcher; they are not imposed by the base parser.

### 4. Route It

```yaml
# config/packages/framework.yaml
framework:
    webhook:
        routing:
            stripe:                                   # → POST /webhook/stripe
                service: App\Webhooks\Stripe\PaymentIntentSucceededRequestParser
                secret: '%env(STRIPE_WEBHOOK_SECRET)%'
```

```yaml
# config/routes/webhook.yaml — make sure your kernel actually imports config/routes/*.yaml
webhook:
    resource: '@FrameworkBundle/Resources/config/routing/webhook.php'
    prefix: /webhook
```

> On Symfony 6.4 and 7.0–7.2 that file is `webhook.xml`: the `.php` variant arrives in 7.3, which is also when the XML one starts warning it is deprecated. Importing the wrong one fails with `Unable to find file "@FrameworkBundle/Resources/config/routing/webhook.php"`.

### 5. Consumer

```php
namespace App\Webhooks\Stripe;

use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Infrastructure\Webhook\ConsumesWebhookEvents;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;

#[AsRemoteEventConsumer('stripe')]
final class PaymentIntentSucceededConsumer implements ConsumerInterface
{
    use ConsumesWebhookEvents;

    protected function mapper(): AbstractWebhookMapper
    {
        return new PaymentIntentSucceededEventMapper();
    }
}
```

`ConsumesWebhookEvents` brings the constructor (it takes the `WebhookEventDispatcher`), `consume()`, and the check that skips events of another type arriving at the same URL — see *One parser per event type* below. A provider that names the type somewhere other than `payload['type']` overrides `handles()`.

### 6. Listener

```php
namespace App\Billing\Infrastructure\EventListener;

use App\Webhooks\Stripe\PaymentIntentSucceededEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class PaymentIntentSucceededListener
{
    #[AsEventListener]
    public function __invoke(PaymentIntentSucceededEvent $event): void
    {
        // Translate the DTO into domain terms here — keep mappers dumb.
    }
}
```

## Things to Know

### One parser per event type

`IntegrationWebhookRequestParser` names every `RemoteEvent` after `getDefinition()`, whatever type the payload declares. If the provider sends several event types to the same URL, check the type in the consumer (as above) and ignore the rest, or give each event type its own URL / routing key when the provider allows it. Don't return `null` from the parser to skip an event: Symfony answers `406` and the provider will keep retrying.

### Request headers

`RemoteEvent` doesn't carry request headers, so a mapper driven from a consumer receives whatever you pass to `WebhookEventDispatcher::dispatch()` (usually `[]`). A mapper that needs a header has to be driven from somewhere that still holds the `Request` — your own controller calling `WebhookEventDispatcher::dispatch()` with the headers you pick out of it.

### Signature verifiers

| Verifier | Scheme | Default header |
|---|---|---|
| `HmacSha256SignatureVerifier($header, $prefix)` | `{prefix}{hex HMAC-SHA256(body)}`; prefix `sha256=` when empty | — |
| `TimestampedHmacSignatureVerifier($header, $toleranceSeconds, ClockInterface)` | Stripe: `t={ts},v1={hex HMAC-SHA256("{ts}.{body}")}`; rejects timestamps outside the tolerance; any matching `v1` passes (key rotation) | — |
| `Base64HmacSignatureVerifier($header)` | base64 HMAC-SHA256(body), sent whole | `X-Shopify-Hmac-SHA256`, `X-WC-Webhook-Signature` |

Any other scheme: implement `SignatureVerifierInterface` (`verify($body, $signature, $secret)` and `getHeaderName()`).

### YAML webhook definitions

`YamlConfigAdapter` reads an optional `webhooks:` section from the **integration's own YAML** (the file under `config_path`), exposed through `ConfigPort::getWebhookDefinition()`. Nothing in the request flow reads it; it is metadata you can consume yourself.

```yaml
# src/Integrations/Stripe/Stripe.yaml
webhooks:
    payment_intent.succeeded:
        mapper: App\Webhooks\Stripe\PaymentIntentSucceededEventMapper
        signature:
            type: timestamped_hmac        # or hmac_sha256
            header: Stripe-Signature
            timestamp_tolerance: 300      # required for timestamped_hmac; not allowed for hmac_sha256
```

`webhooks:` is **not** a key of the bundle config (`integration_engine.integrations.<name>`); putting it there fails with `Unrecognized option "webhooks"`.

## Testing

Post a correctly signed request through the real stack and assert on the typed event your listener receives:

```php
use App\Webhooks\Stripe\PaymentIntentSucceededEvent;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class StripeWebhookTest extends WebTestCase
{
    public function testSignedEventReachesListeners(): void
    {
        $client = self::createClient();
        $received = [];
        self::getContainer()->get(EventDispatcherInterface::class)->addListener(
            PaymentIntentSucceededEvent::class,
            static function (PaymentIntentSucceededEvent $event) use (&$received): void { $received[] = $event; },
        );

        $body = json_encode([
            'id' => 'evt_1',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_1', 'amount' => 500, 'currency' => 'usd']],
        ], \JSON_THROW_ON_ERROR);
        $t = time();
        $signature = \sprintf('t=%d,v1=%s', $t, hash_hmac('sha256', "{$t}.{$body}", 'whsec_test')); // = STRIPE_WEBHOOK_SECRET in the test env

        $client->request('POST', '/webhook/stripe', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], content: $body);

        self::assertResponseStatusCodeSame(202);
        self::assertCount(1, $received);
    }
}
```

Also worth covering: a forged signature and an expired timestamp (both `406`), and an event type you don't handle (`202`, nothing dispatched).

## Best Practices

1. **Verify before trusting**: the parser rejects the request before any payload is decoded or mapped.
2. **Keep mappers dumb**: map to the DTO; translate to domain objects in the listener.
3. **Expect replays**: providers retry, so make listeners idempotent (or use `WebhookIdempotencyService` with your own storage).
4. **Test with real payloads**: record them from the provider and replay them with valid signatures.
5. **Keep secrets out of Git**: use env vars / a vault for webhook secrets.

## See Also

- **CLAUDE.md**: Architecture overview and command reference
- **tests/Infrastructure/Webhook/**: parser, consumer trait and idempotency tests
- **src/Core/Contract/Webhook/**: Ports and mapper contracts
