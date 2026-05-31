<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

interface ResponseInterface
{
    public function toArray(): array;
}
