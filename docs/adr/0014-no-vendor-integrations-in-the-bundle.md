# 0014 · The bundle ships schemes, not integrations

- **Status:** Accepted
- **Date:** 2026-09-20
- **Supersedes:** part of [0009](./0009-inbound-webhooks.md) (the built-in verifier list and the shipped platform integrations)

## Context

The webhook side of the bundle grew two kinds of code under the same roof:

**Mechanism** — the request parser base class, the signature schemes, the mapper contract, the event dispatcher, the idempotency and dead-letter contracts, `make:webhook`. None of it knows who is calling.

**Integrations** — `ShopifyHmacSignatureVerifier`, `WooCommerceHmacSignatureVerifier`, two Shopify parsers, six mappers, six event DTOs, `ShopifyWebhookController`, and the multi-platform routing trio (`WebhookPlatform`, `WebhookPlatformConfig`, `WebhookPlatformRegistry`, `MultiPlatformWebhookController`). Twenty files that each name one vendor.

Three things made the split worth settling:

1. **The vendor names hid a duplication.** `ShopifyHmacSignatureVerifier` and `WooCommerceHmacSignatureVerifier` are the same code twice — a raw HMAC-SHA256 digest, base64-encoded, sent whole — differing only in a default header. Named after the scheme instead (`Base64HmacSignatureVerifier`), one class covers both and anyone else who signs that way.
2. **A shipped integration is a promise.** `ShopifyOrderCreatedMapper` maps six fields of one version of one provider's payload. When Shopify adds a field, the bundle owes a release; when a user needs a seventh field, they cannot get it without forking. The bundle cannot hold a vendor's API surface stable for them.
3. **They were not a working path anyway.** `ShopifyWebhookController` routed on `X-Shopify-Topic`, a header no HMAC covers. `MultiPlatformWebhookController` verified and acknowledged webhooks but never dispatched them (deprecated in 5.4.0). Neither is autowirable, so both needed explicit registration nobody was doing.

## Decision

**The bundle ships what is provider-agnostic. Anything that names a vendor belongs in the application.**

Kept, because none of it names anyone:

- `IntegrationWebhookRequestParser`, `AbstractWebhookMapper`, `WebhookEventInterface`, `SignatureVerifierInterface`
- The three signature schemes: `HmacSha256SignatureVerifier` (hex behind a prefix), `Base64HmacSignatureVerifier` (raw digest in base64), `TimestampedHmacSignatureVerifier` (signed timestamp with a tolerance)
- `WebhookEventDispatcher`, `ConsumesWebhookEvents`, `WebhookEventRegistry`
- The contracts an application backs with its own storage: idempotency, dead-letter queue, audit trail, mapper resolution
- `make:webhook`, which writes the vendor-specific classes into the application, where they can be edited

Removed in 6.0.0: the twenty files listed above.

## Consequences

- **It breaks.** Anyone using the shipped Shopify or WooCommerce classes has to bring them into their own codebase. `make:webhook` generates equivalents, and UPGRADE-6.0.md carries the mapping.
- A webhook costs one `make:webhook` run and one routing entry per event type — the same as before, minus the illusion that the bundle already knew the provider.
- The signature schemes stay first-class: a new provider is covered as long as it signs in one of the three shapes, and `SignatureVerifierInterface` is there when it does not.
- The bundle stops being a place where a vendor's API changes force a release.

## Alternatives considered

- **Deprecate now, delete in 7.0.** The usual courtesy, and the right call with users on those classes. There are none: the multi-platform path never dispatched an event, and the Shopify parsers were not autowirable. A deprecation cycle would have carried dead code for a major.
- **Move them to a separate package** (`integration-engine-shopify`). Same maintenance promise, one repository further away. Worth revisiting only if the demand shows up — and then it is a package with its own release cycle, not a corner of this one.
