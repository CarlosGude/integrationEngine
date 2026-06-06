<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use IntegrationEngine\Core\Contract\ResponseInterface;

final class FakeProtectedResponse implements ResponseInterface
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [];
    }
}
