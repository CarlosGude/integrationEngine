<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use IntegrationEngine\Core\Contract\Action\FormEncodedBodyInterface;

final readonly class FakeFormBody implements FormEncodedBodyInterface
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
