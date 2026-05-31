<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

interface ActionBodyInterface
{
    public function toArray(): array;
}
