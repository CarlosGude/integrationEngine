<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Action;

interface ActionContextInterface
{
    /** @param array<string, mixed> $data */
    public static function create(array $data): self;

    /** @return array<string, mixed> */
    public function toArray(): array;
}
