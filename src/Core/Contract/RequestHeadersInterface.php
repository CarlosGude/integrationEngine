<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

interface RequestHeadersInterface
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array;
}
