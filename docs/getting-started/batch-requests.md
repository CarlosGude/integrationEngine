# Batch requests

`sendMany()` executes several `EngineRequest` values while preserving each input key and isolating failures. It uses a client's batch capability when available and otherwise falls back to individual sends.

## Build and dispatch a batch

```php
use IntegrationEngine\Core\Batch\EngineRequest;
use IntegrationEngine\Core\Contract\Action\DefaultActionContext;

$requests = [
    'lon' => new EngineRequest(
        GetAccommodationAction::getName(),
        context: DefaultActionContext::create(['id' => 101]),
    ),
    'par' => new EngineRequest(
        GetAccommodationAction::getName(),
        context: DefaultActionContext::create(['id' => 202]),
    ),
];

$results = $engine->sendMany($requests);
```

An `EngineRequest` can carry the same per-call inputs as `send()`: action name, context, body, headers, base URL override and connection value.

## Results and failure semantics

`sendMany()` returns `BatchResultCollection`. Every input key gets one `BatchResult`; one failure does not cancel unrelated items.

```php
if ($results['lon']->isSuccess()) {
    $response = $results['lon']->response();
} else {
    $error = $results['lon']->error();
}

foreach ($results->errors() as $key => $error) {
    // application-owned failure policy
}
```

`responses()` returns successful mapped responses and `errors()` returns failures, both keyed like the input.

For strict handling:

```php
$responses = $engine->sendManyOrFail($requests);
```

The complete batch is dispatched first; `sendManyOrFail()` then unwraps results in input order and throws when it reaches the first failed item.

## Concurrency

Concurrency is a client capability, represented by `BatchClientInterface`. The three built-in transports implement it:

| Built-in client | Batch behavior |
|---|---|
| `rest` | concurrent HTTP dispatch |
| `graphql` | concurrent HTTP dispatch |
| `form_encoded` | concurrent HTTP dispatch through the REST transport |

For REST and GraphQL, requests are dispatched before responses are consumed. The form adapter delegates batch work to the same REST transport.

When `request_middlewares` are configured on a built-in client, batch execution deliberately falls back to per-item sequential `send()`. A request middleware can inspect, replace or short-circuit a completed request, so the transport cannot preserve the same middleware semantics while blindly pre-dispatching every item.

A custom `client_service` or adapter is concurrent only if it implements `BatchClientInterface`; otherwise the engine transparently uses sequential `ClientInterface::send()` calls.

## Mixed actions and connections

A batch may contain different actions and different runtime connections:

```php
$results = $engine->sendMany([
    'orders-acme' => new EngineRequest(GetOrdersAction::getName(), connection: 'acme'),
    'profile' => new EngineRequest(GetProfileAction::getName(), connection: 'globex'),
]);
```

Each item resolves its own action, connection, authorization and mapper. Connection resolution is memoized within one `sendMany()` call for repeated equivalent connection values, avoiding redundant resolver calls.

Items are grouped by resolved base URL before batch dispatch. That keeps dynamic endpoint overrides compatible with clients that implement both `BatchClientInterface` and `DynamicBaseUrlClientInterface`.

## Homogeneous batch mapping

`AbstractBatchMapper` is optional. Use it when all successful results belong to the same action and the application wants to consolidate them into one response object:

```php
use IntegrationEngine\Core\Batch\AbstractBatchMapper;
use IntegrationEngine\Core\Batch\BatchResultCollection;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final class AccommodationListBatchMapper extends AbstractBatchMapper
{
    public static function getAction(): string
    {
        return GetAccommodationAction::class;
    }

    protected static function consolidate(BatchResultCollection $results): ResponseInterface
    {
        if ($results->hasFailures()) {
            throw array_values($results->errors())[0];
        }

        return new AccommodationListResponse($results->responses());
    }
}

$list = $engine->sendMany($requests)->mapWith(AccommodationListBatchMapper::class);
```

For mixed-action batches, consume `BatchResultCollection` directly instead of forcing a common batch mapper.
