<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

interface ResponseInterface
{
    /** @return array<string, mixed> */
    public function toArray(): array;
}
