<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

interface ActionBodyInterface
{
    /** @param array<string, mixed> $data */
    public static function create(array $data): self;

    /** @return array<string> */
    public static function keys(): array;

    /** @return array<string, mixed> */
    public function toArray(): array;
}
