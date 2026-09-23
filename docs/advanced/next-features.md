# Proposed next features

These items are **not implemented**. Features already shipped in v8 (host/private-network protection, retry/timeout configuration and PHPStan rules) were removed from this proposal document so it does not describe released code as future work.

## Application-selected Symfony transport

Today bundle-managed REST, GraphQL and form adapters are built on the application's `http_client` service plus per-integration decorators/options. Applications can replace the entire engine-facing client with `client_service`, but cannot select a different Symfony `HttpClientInterface` service while retaining the built-in adapter wiring.

A possible extension is an optional `http_client_service` integration setting that supplies the transport used underneath the built-in protocol adapter.

Acceptance criteria before shipping:

- cannot be combined ambiguously with `client_service`;
- works for REST, GraphQL and form adapters;
- runtime base-URL overrides, token requests, batches, host policy and request middleware still use the selected transport;
- invalid/missing transport services fail at container build time with a useful message;
- current default `http_client` behavior remains unchanged.

This is an extensibility feature, not another SSRF feature: v8 already provides `allowed_hosts` and `block_private_networks` for the bundle-managed transport.

## Metrics exporter

The current event model already emits per-operation/per-batch-item `ResponseMapped` and `RequestFailed` events with duration, status and `requestKey`, and `ObservabilitySetup` can call an application callback.

A first-party Prometheus-style exporter would still need a deliberate ownership model for registry/storage across PHP workers and for the scrape endpoint. The bundle should not silently create process-local metrics that look global.

A future exporter should keep labels bounded (`integration`, `action`, outcome/status class) and exclude connection IDs, URLs, payload data, exception messages and credentials.

Before implementation, decide:

- which optional metrics library, if any, is supported;
- how storage is shared between workers;
- how listener failures affect counting;
- whether webhook counters belong in the same exporter;
- how the consuming application exposes the scrape endpoint.

Until then, consume lifecycle events directly in the application.
