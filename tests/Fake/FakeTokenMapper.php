<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Mapper\AbstractMapper;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final class FakeTokenMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return FakeTokenAction::class;
    }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        /** @var array<string, mixed> $response */
        return new FakeTokenResponse($response);
    }
}
