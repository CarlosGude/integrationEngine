# Resilience

IntegrationEngine has two distinct resilience layers. Keeping them separate avoids implying that application retry utilities automatically wrap engine calls.

## Managed HTTP transport retries

For built-in clients, `integration_engine.yaml` can configure Symfony's managed transport retry policy:

```yaml
integration_engine:
    integrations:
        my_api:
            retry:
                max_retries: 3
                delay_ms: 200
                multiplier: 2
                max_delay_ms: 2000
                jitter: 0.1
                status_codes: [423, 425, 429, 500, 502, 503, 504, 507, 510]
                retry_non_idempotent: false
```

This policy belongs to the HTTP transport constructed by the bundle. It is not available with `client_service`, because a custom client owns its own transport behavior. Non-idempotent methods are not retried unless explicitly enabled.

See [resilience-v8.md](../resilience-v8.md) for the detailed transport classification and configuration introduced for v8.

## Application-owned resilience utilities

The core also exposes framework-independent classification/backoff contracts for code that owns a retry loop outside the engine transport:

- `EngineErrorClassifier` and `ErrorClassifierInterface`;
- `ErrorClassification`;
- `ExponentialBackoff` and `ResiliencePolicyInterface`;
- `SymfonyErrorClassifier` when Symfony HTTP exception knowledge is useful.

Example:

```php
use IntegrationEngine\Core\Resilience\ExponentialBackoff;
use IntegrationEngine\Infrastructure\Resilience\SymfonyErrorClassifier;

$policy = new ExponentialBackoff(
    maxRetries: 3,
    classifier: new SymfonyErrorClassifier(),
);

if ($policy->shouldRetry($error, $retryNumber)) {
    $delayMs = $policy->getBackoffMs($retryNumber);
    // The application decides how and when to wait/retry.
}
```

These objects do not intercept `IntegrationEngine::send()` automatically. They are building blocks for application orchestration.

## Dynamic-auth 401 retry

Dynamic authorization has one separate, narrowly scoped retry: when a cached token is rejected with 401, the engine evicts it, fetches a new token and retries the protected request once. This is credential refresh behavior, not the general transport retry policy. See [Authorization](../getting-started/authorization.md).

## Compatibility names

Legacy `ErrorClassifier` and `ExponentialBackoffPolicy` names remain in the compatibility layer. New code should use the core contracts/classes above; compatibility exists to preserve migrations, not as a parallel API to document independently.
