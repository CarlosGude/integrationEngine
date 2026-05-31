<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Support\Fixtures\GetHelloWorld\Response;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\AbstractMapper;
use IntegrationEngine\Core\Contract\ResponseInterface;
use IntegrationEngine\Tests\Support\Fixtures\GetHelloWorld\Request\GetHelloWorldAction;

final class GetHelloWorldMappper extends AbstractMapper
{
    public static function getAction(): string
    {
        return GetHelloWorldAction::class;
    }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return new GetHelloWorldResponse();
    }
}