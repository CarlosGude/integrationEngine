# Declarative HTTP resilience

Built-in HTTP integrations accept `timeout` (inactivity), `max_duration` (total request duration) and optional `retry`. Omitting retry leaves the plain transport. Custom `client_service` owns its transport and cannot combine these options.

```yaml
integration_engine:
  integrations:
    supplier:
      base_url: 'https://supplier.example'
      config_path: '%kernel.project_dir%/config/integrations/supplier.yaml'
      timeout: 2.0
      max_duration: 10.0
      retry:
        max_retries: 3
        delay_ms: 200
        multiplier: 2.0
        max_delay_ms: 2000
        jitter: 0.1
        status_codes: [423, 425, 429, 500, 502, 503, 504, 507, 510]
        retry_non_idempotent: false
```

Each configured HTTP status and transport error (code zero) retries only GET, HEAD, PUT, DELETE, OPTIONS and TRACE by default. Setting `retry_non_idempotent: true` allows any method, including POST. Use it only when the provider supports an idempotency key and reuse the same key across all attempts; supply it through `RequestHeadersInterface`.

Symfony has one explicit exception: a DNS failure with an empty `primary_ip` is retried before consulting the strategy, including POST, because no remote HTTP request has been delivered. This behavior is shared by the supported versions.

`Retry-After` overrides exponential delay, supporting seconds or an HTTP date. The bundle delegates this to Symfony; no duplicate retry scheduler is introduced. The test records the pause handler requested by Symfony, without sleeping. A max retry count of three permits four attempts in total.

Action YAML may set `timeout` to override the integration inactivity timeout. `AbstractAction::getTimeout()` and the fully built `Request` preserve the override through header middleware and authorization rebuilding. Batch transport starts every initial request before consuming responses. Request middlewares retain the documented sequential fallback because they can synchronously inspect or replace responses.

Source verification against exact Symfony tags:

| Version | Status/method map and transport errors | Retry-After and DNS exception |
|---|---|---|
| 6.4.0 | [GenericRetryStrategy, lines 77–98](https://github.com/symfony/symfony/blob/v6.4.0/src/Symfony/Component/HttpClient/Retry/GenericRetryStrategy.php#L77) | [RetryableHttpClient, lines 99–181](https://github.com/symfony/symfony/blob/v6.4.0/src/Symfony/Component/HttpClient/RetryableHttpClient.php#L99) |
| 7.4.0 | [GenericRetryStrategy, lines 70–91](https://github.com/symfony/symfony/blob/v7.4.0/src/Symfony/Component/HttpClient/Retry/GenericRetryStrategy.php#L70) | [RetryableHttpClient, lines 99–181](https://github.com/symfony/symfony/blob/v7.4.0/src/Symfony/Component/HttpClient/RetryableHttpClient.php#L99) |
| 8.0.0 | [GenericRetryStrategy, lines 70–91](https://github.com/symfony/symfony/blob/v8.0.0/src/Symfony/Component/HttpClient/Retry/GenericRetryStrategy.php#L70) | [RetryableHttpClient, lines 99–181](https://github.com/symfony/symfony/blob/v8.0.0/src/Symfony/Component/HttpClient/RetryableHttpClient.php#L99) |

See [behavior tests](../tests/Infrastructure/ResilienceBehaviourTest.php), [concurrent retry test](../tests/Infrastructure/ResilienceBatchTest.php) and [factory tests](../tests/Infrastructure/RetryStrategyFactoryTest.php).
