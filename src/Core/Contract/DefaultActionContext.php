<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

final readonly class DefaultActionContext implements ActionContextInterface
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
