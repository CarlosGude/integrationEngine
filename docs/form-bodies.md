# Form bodies

REST actions can declare a body implementing `IntegrationEngine\Core\Contract\Action\FormEncodedBodyInterface`. Its `toArray()` payload is submitted as `application/x-www-form-urlencoded`, including bracket notation for nested arrays. Ordinary `ActionBodyInterface` bodies remain JSON. POST, PUT and PATCH send bodies; other methods omit them.

```php
use IntegrationEngine\Core\Contract\Action\FormEncodedBodyInterface;

final readonly class TokenBody implements FormEncodedBodyInterface
{
    /** @param array<string, mixed> $data */
    private function __construct(private array $data) {}

    public static function create(array $data): self
    {
        return new self($data);
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
```

Configure the body on a REST action exactly like a JSON body. Encoding follows its contract, so one integration and one batch can contain both formats. Request middlewares receive the structured array and `BodyEncoding::Form`; adding a header with `Request::withHeader()` preserves encoding and timeout.

The `form_encoded` client selector remains useful for providers whose entire API uses forms. It delegates the same REST implementation with form encoding as its default, including batch dispatch and request middleware support. There is one HTTP serialization and response handling implementation.

GraphQL requires `GraphQLBodyInterface` and rejects form bodies explicitly. Multipart uploads are outside this contract.

Executable examples: [form transport tests](../tests/Infrastructure/SymfonyHttpClientAdapterFormBodyTest.php), [request contract](../tests/Core/RequestEncodingTest.php), [GraphQL rejection](../tests/Infrastructure/GraphQLFormBodyTest.php).
