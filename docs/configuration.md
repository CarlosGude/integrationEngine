# Bundle configuration

IntegrationEngine has two configuration scopes, and they answer different questions:

```text
config/packages/integration_engine.yaml   (bundle configuration)
└── how each integration is wired: base_url, client, transport,
    headers, cache, middlewares, request_middlewares, connection_resolver

src/.../MyApi.yaml                        (integration YAML)
├── which actions it exposes: class, method, path, body,
│   authorization, cache_ttl, timeout
├── optional defaults: shared authorization
└── optional webhooks: inbound event definition
```

Putting a client selector in an action entry, or an action class in bundle configuration, mixes the two scopes and is not part of the contract.

This page is the reference for the bundle configuration. The integration YAML is documented where each entry is explained: action keys in [Actions](./getting-started/actions.md#action-yaml), `defaults:` in [Authorization](./getting-started/authorization.md#sharing-authorization-across-actions) and `webhooks:` in [Webhooks](./webhook.md#1-define-the-webhook-contract-in-integration-yaml).

## Keys

Every key is set per integration, under `integration_engine.integrations.<name>`.

| Key | Default | Meaning | Details |
|---|---|---|---|
| `config_path` | — | Path to the integration YAML. Required. | |
| `base_url` | — | Base URL used by the bundle-managed adapters. Required unless `client_service` is set. | |
| `client` | `rest` | Adapter type: `rest`, `graphql`, `form_encoded` or a tagged custom type. | [HTTP clients](./advanced/architecture/clients.md) |
| `client_service` | — | Application `ClientInterface` service that replaces the bundle-managed transport. | [HTTP clients](./advanced/architecture/clients.md#client-vs-client_service) |
| `headers` | `{}` | Headers sent with every request of this integration. | [Header precedence](./advanced/architecture/clients.md#header-precedence) |
| `cache_service` | `integration_engine.cache.default` | `CachePort` service for dynamic tokens and response caching. The default is PSR-6 over Symfony `cache.app`. | [Authorization](./getting-started/authorization.md#cache-backend) |
| `connection_resolver` | — | `ConnectionResolverInterface` service for the `connection` argument. | [Runtime connections](./advanced/architecture/clients.md#runtime-connection-resolution) |
| `middlewares` | `[]` | Ordered `AbstractClientMiddleware` service IDs, outermost first. | [Middleware](./middleware.md) |
| `request_middlewares` | `[]` | Ordered `RequestMiddlewareInterface` service IDs, outermost first. Built-in clients only. | [Middleware](./middleware.md#request-middleware) |
| `timeout` | none | Transport inactivity timeout, in seconds. | [Resilience](./resilience.md) |
| `max_duration` | none | Maximum total duration of a request, in seconds. | [Resilience](./resilience.md) |
| `retry` | off | Symfony transport retry policy. | [Resilience](./resilience.md) |
| `allowed_hosts` | `[]` (any host) | Hostname allowlist; supports `*.example.com`. | [Security](./security.md) |
| `block_private_networks` | `false` | Wraps the transport with Symfony `NoPrivateNetworkHttpClient`. | [Security](./security.md) |
| `profiler_payloads` | `false` | Shows body, context and successful response contents in the profiler. Only active while `kernel.debug` is true. | [Debugging](./advanced/debugging.md#opt-in-showing-body-context-and-response-contents) |

## Validation

The bundle configuration fails at container build time when:

- an integration defines neither `base_url` nor `client_service`;
- `client_service` is combined with `retry`, `timeout`, `max_duration` or `block_private_networks` — the engine does not control that transport;
- `client` is empty;
- an `allowed_hosts` entry contains a scheme, port or path;
- a `retry` value is out of range (`max_retries` ≥ 1, `multiplier` ≥ 1, `jitter` between 0 and 1, status codes between 100 and 599);
- a service listed under `middlewares` or `request_middlewares` is not tagged `integration_engine.middleware` / `integration_engine.request_middleware`.

The integration YAML is parsed by `YamlConfigAdapter` when the integration service is created. Missing files, invalid action definitions, invalid timeouts and authorization blocks, and malformed webhook definitions fail explicitly at that point, before any request is sent.

## Full example

```yaml
# config/packages/integration_engine.yaml
integration_engine:
    integrations:
        acme:
            config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/Acme/Acme.yaml'
            base_url: 'https://api.acme.test'
            client: rest
            headers:
                Accept-Language: en
            timeout: 10
            max_duration: 30
            retry:
                max_retries: 3
                delay_ms: 200
                multiplier: 2.0
                max_delay_ms: 2000
                jitter: 0.1
                status_codes: [423, 425, 429, 500, 502, 503, 504, 507, 510]
                retry_non_idempotent: false
            allowed_hosts: ['api.acme.test', '*.cdn.acme.test']
            block_private_networks: true
            cache_service: integration_engine.cache.default
            connection_resolver: App\Infrastructure\Integrations\Acme\AcmeConnectionResolver
            middlewares:
                - App\Infrastructure\Http\AuditMiddleware
            request_middlewares:
                - App\Infrastructure\Http\SigningMiddleware
```

To inspect what is configured without sending requests, use [`debug:integration`](./advanced/debugging.md#inspect-configured-integrations).
