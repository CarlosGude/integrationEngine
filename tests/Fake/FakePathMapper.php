<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\AbstractMapper;
use IntegrationEngine\Core\Contract\ResponseInterface;

final class FakePathMapper extends AbstractMapper
{
    public static function getAction(): string { return FakePathAction::class; }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return new FakePathResponse();
    }
}
