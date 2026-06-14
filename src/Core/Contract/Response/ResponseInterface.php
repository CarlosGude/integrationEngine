<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Response;

interface ResponseInterface
{
    /** @return array<string, mixed> */
    public function toArray(): array;
}
