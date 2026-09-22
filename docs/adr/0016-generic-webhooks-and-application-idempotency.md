# 0016 · Generic authenticated webhooks and application idempotency

- **Status:** Accepted (parser and domain contracts); transport acknowledgement pending
- **Date:** 2026-09-22

## Context

The previous subclass-per-event parser split payload verification and mapping across the bundle and consumer. Cache deduplication could lose events when a receipt was cached before a failed side effect. Version 8 explicitly permits breaking obsolete contracts.

## Decision

A YAML definition selects a signature scheme, event type/ID dot paths, and static typed mapper. The parser verifies raw bytes before JSON decoding and mapping. It returns a `MappedRemoteEvent` containing the original decoded payload and immutable DTO. Structured rejection messages contain fixed reason codes, never payloads, signatures or secrets. The YAML secret is the only authority. The core depends on PSR Clock, not Symfony.

The bundle exposes provider event IDs and performs no deduplication. Applications use unique durable receipts in the same transaction as side effects. No cache TTL can establish exactly-once delivery.

## Alternatives considered

Per-event generated parsers duplicate security-sensitive logic. Mapping in consumers delays schema failures until asynchronous work and repeats extraction logic. Cache deduplication requires arbitrary TTLs, shared topology assumptions and atomicity it cannot guarantee. Symfony's `IsJsonRequestMatcher` validates JSON before authentication, so we match only HTTP method and media type before signature verification.

## Consequences

Consumers receive typed events directly. v7 instance mapper and verifier APIs change. Symfony's supplied controllers reject null, so an acknowledged-ignore transport requires an explicit adjustment; see the spike before choosing an endpoint. The signature algorithms are independent of that pending HTTP transport choice.

## References

- [Spike with exact Symfony tags](../spikes/webhooks-v8.md)
- [v8 webhook usage and migration](../webhooks-v8.md)
- [Parser](../../src/Infrastructure/Webhook/IntegrationWebhookRequestParser.php)
- [Timestamp boundary tests](../../tests/Core/Webhook/TimestampedHmacSignatureVerifierTest.php)
