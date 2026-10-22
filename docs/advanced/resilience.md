# Application-owned resilience utilities

The bundle retries in two places on its own:

- the **transport retry policy** configured per integration — see [Declarative HTTP resilience](../resilience.md);
- a **single retry when a cached dynamic token is rejected with 401** — see [Authorization](../getting-started/authorization.md#rejected-cached-tokens).

Everything else — retrying a whole business operation, falling back, giving up — belongs to the application. For that, Core exposes framework-independent classification and backoff building blocks. They do **not** wrap `IntegrationEngine::send()` automatically.

## Classification and backoff

- `ErrorClassifierInterface` turns a `Throwable` into an `ErrorClassification`.
- `ErrorClassification` treats network errors, HTTP 408, 429 and 5xx as transient, and every other 4xx as permanent.
- `EngineErrorClassifier` classifies `RequestResponseException` by its status code; `SymfonyErrorClassifier` additionally understands Symfony's HTTP and transport exceptions.
- `ResiliencePolicyInterface` and `ExponentialBackoff` decide whether to retry and how long to wait.

```php
use IntegrationEngine\Core\Resilience\ExponentialBackoff;
use IntegrationEngine\Infrastructure\Resilience\SymfonyErrorClassifier;

$policy = new ExponentialBackoff(
    maxAttempts: 3,
    classifier: new SymfonyErrorClassifier(),
);

if ($policy->shouldRetry($error, $retryNumber)) {
    $delayMs = $policy->getBackoffMs($retryNumber);
    // The application decides how and when to wait/retry.
}
```

**Network errors from the engine are not classified as transient.** The built-in adapters wrap network failures as `RequestResponseException` with status `0` ([Errors](../errors.md#requestresponseexception)), and both classifiers read that as a status code, not as a network error. Rely on the declarative transport retry for automatic network retries: it runs before the wrapping.

## Compatibility names

Legacy `ErrorClassifier` and `ExponentialBackoffPolicy` names remain in the compatibility layer ([Architecture](../architecture.md#compatibility-layer)). New code should use the Core contracts above; the compatibility names exist to preserve migrations, not as a parallel API.
