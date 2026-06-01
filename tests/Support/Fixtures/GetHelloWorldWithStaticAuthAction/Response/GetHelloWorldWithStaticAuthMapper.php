<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Support\Fixtures\GetHelloWorldWithStaticAuthAction\Response;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\AbstractMapper;
use IntegrationEngine\Core\Contract\ResponseInterface;
use IntegrationEngine\Tests\Support\Fixtures\GetHelloWorldWithStaticAuthAction\Request\GetHelloWorldWithStaticAuthAction;

final class GetHelloWorldWithStaticAuthMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return GetHelloWorldWithStaticAuthAction::class;
    }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return new GetHelloWorldWithStaticAuthResponse();
    }
}
