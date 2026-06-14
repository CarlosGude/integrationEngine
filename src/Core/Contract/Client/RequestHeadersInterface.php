<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Client;

interface RequestHeadersInterface
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array;
}
