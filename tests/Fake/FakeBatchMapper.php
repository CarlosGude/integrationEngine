<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use IntegrationEngine\Core\Batch\AbstractBatchMapper;
use IntegrationEngine\Core\Batch\BatchResultCollection;
use IntegrationEngine\Core\Contract\ResponseInterface;

final class FakeBatchMapper extends AbstractBatchMapper
{
    public static ?BatchResultCollection $capturedCollection = null;

    public static function getAction(): string
    {
        return FakePathAction::class;
    }

    protected static function consolidate(BatchResultCollection $results): ResponseInterface
    {
        self::$capturedCollection = $results;

        return new FakeTokenResponse(['count' => \count($results)]);
    }
}
