<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Support\Fixtures\GetHelloWorldWithStaticAuthAction\Response;

use IntegrationEngine\Core\Contract\ResponseInterface;

class GetHelloWorldWithStaticAuthResponse implements ResponseInterface
{
    public function toArray(): array
    {
        return ['hello world'];
    }
}
