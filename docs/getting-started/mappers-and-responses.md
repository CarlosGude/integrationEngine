# Mappers and Responses

A mapper transforms the raw HTTP response array into a typed DTO. It is the only place
where external API field names appear in your codebase.

---

## The minimum

Extend `AbstractMapper`, declare which action it belongs to, and implement `transform()`:

```php
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Mapper\AbstractMapper;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final class GetEmployeeMapper extends AbstractMapper
{
    public static function getAction(): string { return GetEmployeeAction::class; }

    protected static function transform(AbstractAction $action, array $response, array $headers): ResponseInterface
    {
        return GetEmployeeResponse::create($response);
    }
}
```

The response DTO must implement `ResponseInterface`. Its factory rejects a response
that breaks the API contract instead of filling the gap with a default:

```php
use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final readonly class GetEmployeeResponse implements ResponseInterface
{
    private function __construct(
        public int     $id,
        public string  $name,
        public ?string $email,
    ) {}

    /** @param array<mixed> $data */
    public static function create(array $data): self
    {
        $id = $data['id'] ?? null;
        if (!\is_int($id) || $id < 1) {
            throw new \UnexpectedValueException('GetEmployee response: "id" is missing or not a positive integer.');
        }

        $name = $data['name'] ?? null;
        if (!\is_string($name) || '' === $name) {
            throw new \UnexpectedValueException('GetEmployee response: "name" is missing or empty.');
        }

        // The API documents email as optional, so absence is a valid value here.
        $email = $data['email'] ?? null;

        return new self($id, $name, \is_string($email) ? $email : null);
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email];
    }
}
```

---

## Required and optional fields

Decide per field from the API contract, not from what PHP can cast. With `$v = $data['x'] ?? null`:

| API type | Required: reject when missing or invalid | Optional: absence is a valid value |
|---|---|---|
| integer | `\is_int($v) ? $v : throw new \UnexpectedValueException(...)` | `\is_int($v) ? $v : null` |
| number | `\is_int($v) \|\| \is_float($v) ? (float) $v : throw ...` | `\is_int($v) \|\| \is_float($v) ? (float) $v : null` |
| boolean | `\is_bool($v) ? $v : throw ...` | `\is_bool($v) ? $v : null` |
| string | `\is_string($v) ? $v : throw ...` | `\is_string($v) ? $v : null` |
| array / list | `\is_array($v) ? $v : throw ...` | `\is_array($v) ? $v : []` — only when the API documents that absence means empty |
| nested object | `\is_array($v) ? NestedDto::create($v) : throw ...` | `\is_array($v) ? NestedDto::create($v) : null` |

A default such as `(int) ($data['x'] ?? 0)` turns a missing, `null`, renamed or malformed
field into a value that looks valid: the DTO is built, nothing fails, and the error surfaces
far from the mapper. Casts hide malformed values as well — `(int) '12abc'` is `12` and
`(string)` of an array is `'Array'`. Use a default only when the API documents that the field
may be absent and the default means exactly what absence means.

Use a dedicated DTO class for nested objects that appear in more than one response, or
that have more than three or four fields. Nested DTOs do **not** implement
`ResponseInterface` — only the top-level class returned from `transform()` does.

---

## What the engine validates, and what your mapper must

The engine rejects a response before your mapper runs when:

- the HTTP status is 400 or higher, or the request fails at the network level;
- the body is not empty and does not decode to a JSON object or array (for example, an HTML maintenance page);
- a GraphQL response carries an `errors` array;
- the mapper's `getAction()` does not match the action, or an action that declares a response has no mapper.

Each of these throws before `transform()` is called ([Errors](../errors.md)).

Everything about the **content** of the response is the mapper's job: which fields exist, their types,
their formats and their allowed values. The engine passes the decoded body to `transform()` unchanged, so a
`200` with `{}`, a renamed field or a string where you expected a number reaches your mapper as is. An empty
body or HTTP 204 arrives as `[]`; with GraphQL, the mapper receives the `data` member, and `[]` when the
response has none. PHPStan does not cover this either: a `@var array{...}` annotation describes the shape
to the analyser without checking it at runtime.

An exception thrown from `transform()` is neither caught nor wrapped. `send()` rethrows it after
dispatching `RequestFailed` — preceded by `ResponseReceived`, which tells you the API answered and the
mapping failed ([Lifecycle events](../lifecycle.md)). In `sendMany()` it becomes that item's failure,
without affecting the other items ([Batch requests](batch-requests.md)).

---

## Response headers

`transform()`'s third argument, `$headers`, is the response's HTTP headers as
`array<string, string[]>` — the same shape Symfony's HttpClient exposes, one
list per header name since a header can repeat. Header names are lowercase,
so look them up as `x-ratelimit-remaining`, not `X-RateLimit-Remaining`. Most
mappers ignore it; it exists for the data some APIs put outside the body:
pagination cursors, rate-limit counters, request/correlation IDs.

```php
protected static function transform(AbstractAction $action, array $response, array $headers): ResponseInterface
{
    $remaining = $headers['x-ratelimit-remaining'][0] ?? null;

    return GetEmployeesResponse::create($response, remaining: $remaining !== null ? (int) $remaining : null);
}
```

Calling `AbstractMapper::map()` directly (outside the engine flow — e.g. in a
unit test for the mapper itself) can omit `$headers`; it defaults to `[]`.

---

## One mapper per action

The engine enforces `$mapper::getAction() === $action::class` before calling
`transform()`. Two action classes cannot share the same mapper — this throws
`MapperActionMismatchException`. The optional [PHPStan rules](../phpstan.md) check the
same pairing statically. An action whose `hasResponse()` is `false` needs no mapper:
the engine returns `EmptyResponse` after the request succeeds.

When two actions return the same response shape, extract the transformation logic into a
dedicated class and delegate from each mapper:

```php
final class EmployeeCollectionTransformer
{
    public static function transform(array $response): EmployeeListResponse { ... }
}

final class GetEmployeesMapper extends AbstractMapper
{
    public static function getAction(): string { return GetEmployeesAction::class; }
    protected static function transform(AbstractAction $action, array $response, array $headers): ResponseInterface
    {
        return EmployeeCollectionTransformer::transform($response);
    }
}

final class FilterEmployeesMapper extends AbstractMapper
{
    public static function getAction(): string { return FilterEmployeesAction::class; }
    protected static function transform(AbstractAction $action, array $response, array $headers): ResponseInterface
    {
        return EmployeeCollectionTransformer::transform($response);
    }
}
```

---

## `toArray()` — engine contract, not your API

`ResponseInterface` requires `toArray()`. The engine uses it internally to extract
dynamic auth tokens from token responses. It is not your public API.

Consumers of your facade should access typed properties directly:

```php
// ✅ correct — typed access
$name = $response->name;

// ❌ wrong — leaks the engine contract
$name = $response->toArray()['name'];
```

The only time `toArray()` is meaningful to you is when writing a token response for
dynamic auth — the field named in `token_field` must appear in `toArray()`.
See [Authorization](authorization.md) for details.
