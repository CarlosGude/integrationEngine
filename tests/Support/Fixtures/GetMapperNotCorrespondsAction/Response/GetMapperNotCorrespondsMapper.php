<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Support\Fixtures\GetMapperNotCorrespondsAction\Response;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\AbstractMapper;
use IntegrationEngine\Core\Contract\ResponseInterface;
use IntegrationEngine\Core\Response\EmptyResponse;
use IntegrationEngine\Tests\Support\Fixtures\GetMapperNotCorrespondsAction\Request\GetMapperNotCorrespondsAction;

class GetMapperNotCorrespondsMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return GetMapperNotCorrespondsAction::class;
    }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return new EmptyResponse();
    }
}
