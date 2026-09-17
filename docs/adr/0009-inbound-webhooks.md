# 0009 · Inbound Webhooks: Design and Parser Contract

- **Status:** Proposed
- **Date:** 2026-09-17

## Context

The bundle needs to support inbound webhooks from external providers (e.g., Stripe, PayPal) with the same uniform pattern as outbound integrations. This requires:

1. Defining a webhook in YAML (action-like syntax)
2. Verifying the signature (HMAC, timestamped-HMAC, etc.)
3. Parsing and mapping the payload to a typed event object
4. Rejecting invalid or unknown webhooks with appropriate HTTP status codes

Symfony 6.4+ provides a webhook framework with `RemoteEventParser`, `RemoteEvent`, and `WebhookController`. This ADR documents the design decisions and constraints based on investigation of Symfony's contract across versions 6.4, 7.4, and 8.x.

See: `docs/spikes/webhooks.md` for the spike investigation.

## Decision

*(To be filled after spike investigation)*

### Parser Contract

- [ ] `AbstractRequestParser::parse()` returns `?RemoteEvent`
- [ ] Rejection model: throw `RejectWebhookException` or silent skip
- [ ] Minimum Symfony version: TBD

### Signature Verification

- [ ] Two built-in verifiers: `HmacSha256SignatureVerifier`, `TimestampedHmacSignatureVerifier` (Stripe's model)
- [ ] Configurable prefixes (e.g., `Stripe-Signature:`)
- [ ] PSR-20 clock for timestamp tolerance

### Mapper Invariant

- [ ] `AbstractWebhookMapper::getDefinition()` (mirrors `AbstractMapper::getAction()`)
- [ ] Mapper linked to a webhook definition in YAML
- [ ] Runtime validation: mismatches throw

## Alternatives considered

*(To be filled after spike)*

## Consequences

*(To be filled after spike)*

## References

- Spike: `docs/spikes/webhooks.md`
- Related: ADR-0010 (Webhook Idempotency)
- Symfony webhook framework: `symfony/webhook` component
