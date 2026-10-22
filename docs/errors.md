# Errors and exceptions

`send()` reports failures by throwing typed exceptions; it never returns an error array. Batches are the exception to that rule: every item keeps its own failure (see [below](#batches)).

## Exceptions

All of them live in `IntegrationEngine\Core\Exception`.

| Exception | Thrown when |
|---|---|
| `RequestResponseException` | The request failed: HTTP status ≥ 400, a GraphQL `errors` array, or a network error. See [below](#requestresponseexception). |
| `ActionNotFoundException` | The action name is not in the integration YAML. |
| `IntegrationNotFoundException` | No integration is registered under that name. |
| `PathResolutionException` | A path placeholder is missing from the context or is not scalar, a custom context resolved an empty path, or the placeholder regex failed. Thrown before the request is sent. |
| `MapperActionMismatchException` | A mapper's `getAction()` does not match the action it receives. |
| `NotMappedActionException` | An action declares a response but `mapper()` returns `null`. |
| `BatchMapperActionMismatchException` | A batch mapper receives results from a different action. |
| `DynamicAuthException` | The token response lacks `token_field`, or its value is not scalar. |
| `ConnectionResolutionException` | A `connection` was passed but the integration has no `connection_resolver`. |
| `DisallowedHostException` | The resolved destination is outside `allowed_hosts`, detected by the engine before dispatch. |
| `WebhookSignatureException` | A webhook signature failed verification; it carries a fixed rejection reason. |
| `WebhookMapperMismatchException` | A webhook mapper's `eventType()` does not match the event being mapped. |

Configuration mistakes in the integration YAML — an invalid authorization block, or a body of a different class than the one the action declares — throw `\InvalidArgumentException` before any request is sent.

## RequestResponseException

It exposes two public properties:

- `statusCode` — the HTTP status. `0` means the failure happened before an HTTP response existed: a network error, a destination blocked by `block_private_networks`, or a final URL that the built-in transport rejected against `allowed_hosts` (for example, one changed by a request middleware).
- `context` — a human-readable description. For HTTP errors it **includes the upstream response body**; for network errors, the transport's message.

```php
use IntegrationEngine\Core\Exception\RequestResponseException;

try {
    $response = $registry->get(MyApiIntegration::NAME)->send(/* ... */);
} catch (RequestResponseException $e) {
    // $e->statusCode, $e->context
}
```

A GraphQL error is reported with the status of the HTTP response, which is usually `200`. Code that classifies failures only by HTTP status must take that into account.

Because `context` and the exception message can contain provider data, do not log or persist them without redaction. Lifecycle events and the profiler deliberately carry only bounded metadata instead (see [Lifecycle events](./lifecycle.md) and [Debugging](./advanced/debugging.md)).

## Batches

`sendMany()` does not throw for individual items: each key resolves to a `BatchResult` that holds either the response or the exception, and `BatchResult::response()` rethrows it. `sendManyOrFail()` executes the whole batch and then throws the first failure in request order. See [Batch requests](./getting-started/batch-requests.md#results-and-failure-semantics).

## Retrying

The bundle retries at two points: the transport retry policy you configure ([Resilience](./resilience.md)), and a single retry when a cached dynamic token is rejected with 401 ([Authorization](./getting-started/authorization.md#rejected-cached-tokens)). For retry loops that your application owns, see the [classification and backoff utilities](./advanced/resilience.md).
