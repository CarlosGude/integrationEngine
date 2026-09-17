# 0009 · Inbound Webhooks: Design and Parser Contract

- **Status:** Accepted
- **Date:** 2026-09-17

## Context

The bundle needs to support inbound webhooks from external providers (e.g., Stripe, PayPal) with the same uniform pattern as outbound integrations. This requires:

1. Defining a webhook in YAML (action-like syntax, but for inbound events)
2. Verifying the signature (HMAC, timestamped-HMAC, etc.)
3. Parsing and mapping the payload to a typed event DTO
4. Rejecting invalid or unknown webhooks with appropriate HTTP status codes

Symfony 6.4+ provides a webhook framework with `AbstractRequestParser`, `RemoteEvent`, and `WebhookController` (automatic routing). Investigation of the contract across versions 6.4, 7.4, and 8.x revealed a stable, well-designed interface with one backward-compatible return-type expansion in 7.4.

See: `docs/spikes/webhooks.md` for the spike investigation.

## Decision

### 1. Leverage Symfony's webhook framework (don't reinvent)

Extend `Symfony\Component\Webhook\Client\AbstractRequestParser` for each webhook integration. Symfony handles:
- HTTP request routing to our parser
- Exception-to-Response conversion (`RejectWebhookException` → 406)
- Event dispatch to Messenger bus
- Null handling (return null to silently ignore)

We implement:
- `getRequestMatcher()`: validate HTTP method, Content-Type, custom headers
- `doParse()`: verify signature, parse payload, instantiate `RemoteEvent`

### 2. Minimum Symfony version: 6.4 LTS

- Constraint: `symfony/webhook: ^6.4` (in suggest; consumers must opt-in)
- 6.4 is stable, LTS, and has all required components
- 7.4 and 8.x are backward compatible (tested in spike)

### 3. Rejection model: throw RejectWebhookException or return null

**Throw `RejectWebhookException(statusCode, message)`** when:
- Signature verification fails (HMAC mismatch, timestamp out of tolerance)
- Request doesn't match expected format (wrong method, missing headers)
- Payload is malformed JSON or missing required fields
- **Default status code: 406** (Not Acceptable) for all validation failures

**Return `null`** when:
- Webhook is for a different integration (e.g., we only handle `payment` events, ignore `charge`)
- Silent skip; framework will still return 406 + "Unable to parse..."

**Status codes:**
- **202:** Success (framework default for `createSuccessfulResponse()`)
- **406:** Rejection — invalid signature, malformed, unknown type (default)
- **404:** Unknown webhook type (WebhookController, not parser's concern)
- Avoid **401/403** (wrong semantics; imply authentication, not validation)

### 4. Signature verifiers (built-in)

Two reusable verifiers:
- `HmacSha256SignatureVerifier`: Simple HMAC-SHA256, configurable header prefix (default: `X-Signature`)
- `TimestampedHmacSignatureVerifier`: Stripe's model — `Timestamp, v1=hash, v1=hash, ...` with clock tolerance

Both marked `final` to prevent subclassing; behavior controlled via constructor.

### 5. Mapper invariant: AbstractWebhookMapper

Mirror `AbstractMapper` for responses:
```php
abstract class AbstractWebhookMapper
{
    abstract public function getDefinition(): string;  // returns YAML definition name
    abstract public function map(array $payload, array $headers): WebhookEventInterface;
}
```

**Runtime validation:** `IntegrationEngine` validates mapper's definition matches the configured webhook (like action ↔ mapper in responses).

**Why:** Prevents silent misconfigurations and enables static analysis (future PHPStan rule).

## Alternatives considered

### 1. Build webhook framework from scratch
**Rejected:** Symfony's framework is stable, production-tested, and integrates with Messenger. Reinventing would duplicate code and break compatibility with Symfony's routing and exception handling.

### 2. Use 202 Accepted for all responses (including rejections)
**Rejected:** 406 (Not Acceptable) is semantically correct for validation failures and follows webhook provider conventions. Providers expect non-2xx to know their webhook was rejected.

### 3. Deduplicate webhooks in the parser
**Rejected:** Parser should be stateless. Deduplication belongs in the consumer (see ADR-0010), after mapping. Allows replay testing and handles concurrent webhook retries correctly.

### 4. Make signature verification optional
**Rejected:** All inbound webhooks must verify signatures to prevent spoofing. It's a security boundary; non-negotiable. Signature verifier is always required in YAML config.

## Consequences

### Positive
- ✅ Webhooks have the same structure as outbound integrations (YAML + mapper)
- ✅ Async processing via Messenger bus (same as engine)
- ✅ Signature verification is mandatory and hardened
- ✅ Idempotency can be handled in consumer without slowing down HTTP response (202 immediate)
- ✅ Type safety: RemoteEvent mapped to WebhookEventInterface (DTO)
- ✅ Compatible across Symfony 6.4–8.x without version branching

### Negative
- ⚠️ Requires `symfony/webhook` and `symfony/remote-event` (but these are small, stable components)
- ⚠️ Developers must implement `AbstractRequestParser::doParse()` for each webhook type (boilerplate, but minimal)
- ⚠️ If a provider changes their signature scheme (e.g., Stripe adds SHA-512 support), signature verifier must be updated or extended

---

## References

- Spike investigation: `docs/spikes/webhooks.md`
- Related decision: ADR-0010 (Webhook Idempotency)
- Symfony webhook component: https://symfony.com/doc/current/webhook.html
- Stripe webhook security model: https://stripe.com/docs/webhooks/best-practices
