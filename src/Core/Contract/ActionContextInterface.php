<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

interface ActionContextInterface
{
    public static function create(array $data): self;

    public function toArray(): array;
}