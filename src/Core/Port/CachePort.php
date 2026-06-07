<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Port;

interface CachePort
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value, int $ttl): void;
}
