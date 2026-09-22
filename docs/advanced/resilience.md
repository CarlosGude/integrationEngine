# Resilience utilities

`ErrorClassifier` and `ExponentialBackoffPolicy` are utilities for application-owned
retry logic. The engine does not invoke them automatically or provide a YAML
retry-policy option. Its existing single retry after rejection of a cached auth
token is a separate mechanism.

The classifier accepts Symfony HTTP exceptions and the engine's
`RequestResponseException`. HTTP 408, 429 and 500–599 are transient; other
400–499 responses are permanent. Unrecognised exceptions are neither transient
nor permanent. Their integer exception codes are not treated as HTTP statuses.

Raw Symfony transport exceptions are transient. Built-in adapters currently wrap
transport failures as `RequestResponseException` with status 0 without retaining
the original cause. Status 0 also represents local preparation failures, so the
classifier intentionally leaves it unclassified. Applications cannot reliably
distinguish these cases using this wrapper alone.

`ExponentialBackoffPolicy` numbers proposed retries from **1**. With its default
configuration it permits retries 1, 2 and 3 for transient errors, and rejects retry
4. The corresponding delays are 100, 200 and 400 milliseconds. The caller owns
execution and waiting, and must decide whether repeating a particular operation
is appropriate, including the API's idempotency requirements.

```php
use IntegrationEngine\Core\Resilience\ExponentialBackoffPolicy;

$policy = new ExponentialBackoffPolicy(maxAttempts: 3, initialBackoffMs: 100);
// In an application-owned retry loop, after a failed call:
if ($policy->shouldRetry($error, $retryNumber)) {
    $delayMs = $policy->getBackoffMs($retryNumber);
    // Schedule the next attempt according to the application's execution model.
}
```

Retry numbers below 1 and invalid constructor values throw
`InvalidArgumentException`. A zero initial delay is supported. Delays exceeding
the integer range throw `OverflowException` instead of becoming negative or zero.
`getFallback()` rethrows the original exception; it does not return cached data.
