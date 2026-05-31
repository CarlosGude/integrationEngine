<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Response;

use IntegrationEngine\Core\Contract\ResponseInterface;

final readonly class EmptyResponse implements ResponseInterface
{
    public function toArray(): array
    {
        return [];
    }
}
