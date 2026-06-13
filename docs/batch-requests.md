# Batch / Parallel Requests

Use `sendMany()` when you need the results of N calls before you can proceed — fetching
a list of accommodations by ID, paginating through a resource, or hydrating a set of
entities from different endpoints at once.

---

## Building the batch

Each item in a batch is an `EngineRequest` — the same four arguments as a single
`send()` call, wrapped as an immutable value object:

```php
use IntegrationEngine\Core\Batch\EngineRequest;
use IntegrationEngine\Core\Contract\DefaultActionContext;

$requests = [
    'lon' => EngineRequest::create(GetAccommodationAction::getName(), DefaultActionContext::create(['id' => 101])),
    'par' => EngineRequest::create(GetAccommodationAction::getName(), DefaultActionContext::create(['id' => 202])),
    'mad' => EngineRequest::create(GetAccommodationAction::getName(), DefaultActionContext::create(['id' => 303])),
];

$results = $engine->sendMany($requests); // BatchResultCollection
```

Keys are arbitrary and preserved throughout — `$results['lon']` corresponds to the
request you passed under `'lon'`.

---

## Reading results — `BatchResultCollection`

`sendMany()` returns a `BatchResultCollection`. Each item is a `BatchResult`:

```php
$results['lon']->isSuccess();  // bool
$results['lon']->response();   // GetAccommodationResponse — throws if the item failed
$results['lon']->error();      // \Throwable|null
```

The collection is iterable and countable:

```php
count($results);               // int
$results->keys();              // ['lon', 'par', 'mad']

foreach ($results as $key => $result) { ... }
```

A single failure never aborts the rest of the batch — every item always resolves.

---

## Failure strategies

`BatchResultCollection` gives you the building blocks; you decide the semantics.

**Strict — all or nothing:**

```php
if ($results->hasFailures()) {
    throw array_values($results->errors())[0];
}

// ->responses() contains only successes, keyed like the input
$accommodations = array_map(fn($dto) => Accommodation::fromDto($dto), $results->responses());
```

**Lenient — process what succeeded, log what failed:**

```php
foreach ($results->errors() as $key => $error) {
    $this->logger->warning('Fetch failed', ['key' => $key, 'error' => $error->getMessage()]);
}

$accommodations = array_map(fn($dto) => Accommodation::fromDto($dto), $results->responses());
```

**Item-by-item — full control:**

```php
foreach ($results as $key => $result) {
    if ($result->isSuccess()) {
        $accommodations[$key] = Accommodation::fromDto($result->response());
    }
}
```

---

## `sendManyOrFail()` — strict shorthand

When you want an exception on the first failure and do not need `BatchResultCollection`:

```php
// Returns array<key, ResponseInterface> or throws the first failure in input order
$responses = $engine->sendManyOrFail($requests);
```

The whole batch is always dispatched before failures are evaluated — no item is skipped.

---

## Concurrency

Real concurrency means all HTTP requests are in-flight simultaneously — dispatched before
any response is read, so a slow item does not block the others.

**Concurrency is independent of the protocol.** REST, GraphQL, and SOAP are all HTTP
under the hood. What determines concurrency is whether the client dispatches all requests
before reading any response — that is the `BatchClientInterface` contract.

The bundle proposes, it does not impose: implement `BatchClientInterface` in your client
to opt in to real concurrency. The built-in `SymfonyHttpClientAdapter` (REST) does;
`GraphQLClientAdapter` does not — by design choice, not by protocol limitation.

| Client | Concurrent? |
|---|---|
| `client: rest` (default) | ✅ `SymfonyHttpClientAdapter` implements `BatchClientInterface` |
| `client: graphql` | ❌ `GraphQLClientAdapter` does not — falls back to sequential |
| `client: <custom adapter>` | up to you — implement `BatchClientInterface` to opt in |
| `client_service:` (custom service) | up to you — implement `BatchClientInterface` to opt in |

### REST — zero configuration

No explicit `client:` key needed. `SymfonyHttpClientAdapter` is the default and gives
real concurrency out of the box:

```yaml
integration_engine:
    integrations:
        booking:
            base_url: 'https://supply-xml.booking.com'
            config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/Booking/Booking.yaml'
```

### GraphQL or SOAP with real concurrency

Use `client_service:` and implement `BatchClientInterface` yourself. Symfony's
`HttpClientInterface` supports async dispatch natively — dispatch all, then consume:

```php
use IntegrationEngine\Core\Contract\BatchClientInterface;
use IntegrationEngine\Core\Contract\ClientInterface;
use IntegrationEngine\Core\Batch\PreparedRequest;

final class ConcurrentGraphQLClient implements ClientInterface, BatchClientInterface
{
    public function send(...): array { ... }

    /** @param array<array-key, PreparedRequest> $requests */
    public function sendMany(array $requests): array
    {
        // 1. dispatch all — store lazy response handles, do not read yet
        $handles = [];
        foreach ($requests as $key => $prepared) {
            $handles[$key] = $this->httpClient->request('POST', $prepared->url, [
                'json' => $prepared->body?->toArray(),
            ]);
        }

        // 2. consume — read responses only after all are in-flight
        $results = [];
        foreach ($handles as $key => $handle) {
            try {
                $results[$key] = $handle->toArray();
            } catch (\Throwable $e) {
                $results[$key] = $e;
            }
        }

        return $results;
    }
}
```

```yaml
integration_engine:
    integrations:
        booking:
            client_service: 'App\Infrastructure\Http\ConcurrentGraphQLClient'
```

---

## Consolidating a homogeneous batch — `AbstractBatchMapper`

When all N requests share the same action (same endpoint, N different contexts), extend
`AbstractBatchMapper` to consolidate the individual DTOs into a single `ResponseInterface`.
This is the second stage of batch mapping — the first stage (raw array → DTO) already ran
per item inside `sendMany()`.

```php
use IntegrationEngine\Core\Batch\AbstractBatchMapper;
use IntegrationEngine\Core\Batch\BatchResultCollection;
use IntegrationEngine\Core\Contract\ResponseInterface;

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

        return new AccommodationListResponse(
            array_map(
                fn(GetAccommodationResponse $dto) => Accommodation::fromDto($dto),
                $results->responses(),
            )
        );
    }
}
```

Invoke it via `BatchResultCollection::mapWith()`:

```php
$list = $this->engine
    ->sendMany($requests)
    ->mapWith(AccommodationListBatchMapper::class); // AccommodationListResponse
```

The engine validates that every resolved item belongs to `GetAccommodationAction` before
calling `consolidate()`. Items that failed during HTTP are passed as failures —
`$results->hasFailures()` covers them. The consolidator decides whether to throw, skip,
or log them.

`AbstractBatchMapper` is for **homogeneous** batches (same action). For mixed-action
batches, process `$results->responses()` directly.

---

## Mixed actions

The batch key is arbitrary — actions do not need to be the same:

```php
$results = $engine->sendMany([
    'employee'   => EngineRequest::create(GetEmployeeAction::getName(), DefaultActionContext::create(['id' => 7])),
    'department' => EngineRequest::create(GetDepartmentAction::getName(), DefaultActionContext::create(['id' => 3])),
]);
```

Each item is mapped by its own action's mapper. The mapper invariant (`getAction() ===
$action::class`) is enforced per item, exactly as in single `send()` calls.
