# Upgrading from v5.x to v6.0

One breaking change: **the bundle no longer ships integrations for specific providers.** It ships the signature schemes, the contracts and the generator; the classes that know a provider's payload now live in your application, where you can edit them.

The reasoning is in [ADR 0014](./docs/adr/0014-no-vendor-integrations-in-the-bundle.md). Outbound integrations are untouched — if you don't receive webhooks, there is nothing to do.

## What was removed

| Removed | Replace with |
|---|---|
| `ShopifyHmacSignatureVerifier`, `WooCommerceHmacSignatureVerifier` | `Base64HmacSignatureVerifier($header)` — the same scheme, with the header as an argument |
| `ShopifyProductUpdatedParser`, `ShopifyOrderCreatedParser` | `php bin/console make:webhook <provider> <event>` |
| The six mappers and six event DTOs under `Infrastructure/Webhook/{Mapper,Event}` | The mapper and DTO the generator writes, edited to your payload |
| `ShopifyWebhookController` | A parser per event type plus `framework.webhook.routing` (see WEBHOOK.md), or your own controller if you need the request headers |
| `MultiPlatformWebhookController` (deprecated in 5.4.0), `WebhookPlatform`, `WebhookPlatformConfig`, `WebhookPlatformRegistry` | Symfony's webhook routing: one key, one parser, one secret per event type |

Everything else stays where it was: `IntegrationWebhookRequestParser`, `AbstractWebhookMapper`, `WebhookEventDispatcher`, `ConsumesWebhookEvents`, `HmacSha256SignatureVerifier`, `TimestampedHmacSignatureVerifier`, `Base64HmacSignatureVerifier`, and `WebhookIdempotencyService` with its fingerprinter.

6.0 also drops the scaffolding that only declared intentions: `WebhookDlqPort`, `WebhookEventAuditPort`, `WebhookMapperResolverPort`, `WebhookFailure`, `WebhookEventState`, `WebhookEventStateTransition`, `WebhookEventRegistry`, `ProcessWebhookMessage` and `ProcessWebhookHandler`. Nothing in the bundle called them, and Symfony's Messenger failure transport already is a dead-letter queue: route `ConsumeRemoteEventMessage` to a transport with `failure_transport` set and retries and dead letters are handled for you.

## Migrating

### 1. Verifiers

```diff
-use IntegrationEngine\Core\Webhook\ShopifyHmacSignatureVerifier;
+use IntegrationEngine\Core\Webhook\Base64HmacSignatureVerifier;

 protected function getSignatureVerifier(): SignatureVerifierInterface
 {
-    return new ShopifyHmacSignatureVerifier();
+    return new Base64HmacSignatureVerifier('X-Shopify-Hmac-SHA256');
 }
```

WooCommerce is the same class with `'X-WC-Webhook-Signature'`.

### 2. Parsers, mappers and event DTOs

Generate the four files and move your logic into the mapper:

```bash
php bin/console make:webhook shopify products/update
# asks for the verifier type: hmac_base64 for Shopify and WooCommerce
```

That writes the event DTO, the mapper, the parser and the consumer under `src/Webhooks/Shopify/`, and prints the routing entry. If you were using the bundle's mappers, copy their `map()` body across — they were six fields of one version of one payload, and you now own them.

### 3. Multi-platform routing

If you were routing several platforms through `MultiPlatformWebhookController`: it verified and acknowledged webhooks but never dispatched them, so nothing downstream of it was running. Give each event type its own routing key:

```yaml
framework:
    webhook:
        routing:
            shopify_products_update:
                service: App\Webhooks\Shopify\ProductsUpdateRequestParser
                secret: '%env(SHOPIFY_WEBHOOK_SECRET)%'
            shopify_orders_create:
                service: App\Webhooks\Shopify\OrdersCreateRequestParser
                secret: '%env(SHOPIFY_WEBHOOK_SECRET)%'
```

Each key is the URL segment the provider posts to, and the name the generated consumer answers to.

### 4. If you were routing by topic header

`ShopifyWebhookController` picked the parser from `X-Shopify-Topic`, a header no HMAC covers. If you need one URL for several topics, write that controller in your own application, and know what you are trusting: verify the signature first, and treat the topic as a hint, not as authorisation.
