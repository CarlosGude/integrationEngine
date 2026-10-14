# Outgoing host and network policy

`allowed_hosts` restricts final outgoing HTTP URLs by exact hostname or leading wildcard (`*.partners.example` permits subdomains, not the apex). Ports do not change the hostname policy. An empty list allows all hosts. The engine checks resolved connection/base URLs before dispatch and the built-in HTTP transport checks again after request middleware changes.

```yaml
integration_engine:
  integrations:
    partner:
      base_url: 'https://api.partner.example'
      config_path: '%kernel.project_dir%/config/integrations/partner.yaml'
      allowed_hosts: ['api.partner.example', '*.partners.example']
      block_private_networks: true
```

When a host allowlist is active, automatic redirects are disabled. Follow a redirect explicitly through a new checked engine request if the application needs it. This keeps every destination subject to the same allowlist, including requests modified by middleware and authorization requests. `block_private_networks` defaults to false and wraps the built-in transport with Symfony `NoPrivateNetworkHttpClient`. A custom client owns its transport and cannot use this option.

Private network rejection is surfaced as `RequestResponseException` with status zero. Tests cover loopback IPv4/IPv6, RFC1918 and cloud metadata link-local addresses, plus a public-to-private redirect. There are no real network calls in these tests.

## Symfony version behavior

The exact historical [6.4.0 implementation](https://github.com/symfony/symfony/blob/v6.4.0/src/Symfony/Component/HttpClient/NoPrivateNetworkHttpClient.php#L49) checks `primary_ip` in transport progress callbacks, including changes across redirects. It does not guarantee rejection before a socket connection is established. An ordinary `MockResponse` alone cannot demonstrate that protection: the test transport must emit the actual connected IP to the supplied callback.

The [7.4.0 implementation](https://github.com/symfony/symfony/blob/v7.4.0/src/Symfony/Component/HttpClient/NoPrivateNetworkHttpClient.php#L71) and [8.0.0 implementation](https://github.com/symfony/symfony/blob/v8.0.0/src/Symfony/Component/HttpClient/NoPrivateNetworkHttpClient.php#L69) additionally resolve and check the initial host and each followed redirect before issuing the next request. They pin the resolved address and retain progress-time checks. Later supported patch releases may backport protections; install maintained security patches rather than relying on the initial tag of a release line.

The [test](../tests/Infrastructure/PrivateNetworkBlockingTest.php) exercises the old transport-progress contract and newer decorator-managed redirects without pretending that MockHttpClient follows redirects itself. For newer versions it supplies `redirect_url` metadata as a real Symfony transport does and verifies the private redirect never reaches a second request.

These controls supplement application input validation and network egress policy. An allowed public hostname is not a guarantee about the content it serves. Proxy configuration and the underlying supported Symfony transport remain part of the deployment trust boundary.
