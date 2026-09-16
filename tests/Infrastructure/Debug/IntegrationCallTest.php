<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Debug;

use IntegrationEngine\Infrastructure\Debug\IntegrationCall;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IntegrationCallTest extends TestCase
{
    #[Test]
    public function cachedDefaultsToFalse(): void
    {
        $call = new IntegrationCall(
            integrationName: 'my_api',
            actionName: 'get_items',
            method: 'GET',
            path: '/items',
            durationMs: 12.0,
            error: null,
            statusCode: 200,
        );

        self::assertFalse($call->cached);
    }
}
