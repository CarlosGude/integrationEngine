# v8 webhook contract and compatibility notes

This page records what is specific to the v8 webhook design. For implementation steps, use [WEBHOOK.md](./WEBHOOK.md).

## What changed in v8

- Webhook definition is read from the integration YAML (`type_field`, `id_field`, signature, unknown-event policy and event mapper table).
- The parser verifies raw bytes before JSON decoding.
- A declared event is mapped immediately into `MappedRemoteEvent`, which retains both the raw decoded payload and the typed `WebhookEventInterface` DTO.
- Mapper event names are validated against the YAML key.
- Rejections expose fixed reason codes and never embed secret/signature/payload data.
- Legacy bundle-owned webhook idempotency was removed; durable deduplication belongs to the consuming application.

## Symfony transport finding

The compatibility spike checked Symfony 6.4.0, 7.4.0 and 8.0.0. A parser returning `?RemoteEvent` is compatible with the parser contract across those versions, but the supplied Symfony webhook controller treats a null/empty parse result as a rejection rather than a portable 2xx acknowledgement.

That matters for `unknown_events: ignore`: the parser itself returns `null`, but applications that require an authenticated unknown event to be acknowledged with 2xx should own that transport/controller decision instead of assuming Symfony's default controller will do it.

The archived source investigation is in [archived/spikes/webhooks.md](./archived/spikes/webhooks.md).

## Current application boundary

The bundle stops after authentication/parsing/mapping and Symfony transport integration. It does not own:

- provider-specific business handlers;
- exactly-once/idempotency storage;
- replay tooling;
- a second DLQ abstraction on top of the application's Messenger setup.

That keeps the bundle generic and avoids a cache receipt being mistaken for a transactional guarantee.
