<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Support\Fixtures\GetHelloWorld\Response;

use IntegrationEngine\Core\Contract\ResponseInterface;

class GetHelloWorldResponse implements ResponseInterface
{
    public function toArray(): array
    {
        return ['hello world'];
    }
}
