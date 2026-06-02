<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

interface ActionBodyInterface
{
    public static function create(array $data): self;

    public static function keys(): array;

    public function toArray(): array;
}
