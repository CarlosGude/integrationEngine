<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Response;

use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final readonly class EmptyResponse implements ResponseInterface
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [];
    }
}
