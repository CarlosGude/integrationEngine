<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure\Webhook;

use IntegrationEngine\Infrastructure\Webhook\DotPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DotPathTest extends TestCase
{
    #[DataProvider('provideResolvesOnlyScalarValuesCases')]
    public function testResolvesOnlyScalarValues(string $path, bool|float|int|string|null $expected): void
    {
        self::assertSame($expected, DotPath::get(['id' => 123, 'data' => ['object' => ['id' => 'evt', 'ok' => false]], 'ratio' => 1.5], $path));
    }

    /** @return iterable<array{string, null|bool|float|int|string}> */
    public static function provideResolvesOnlyScalarValuesCases(): iterable
    {
        yield ['id', 123];

        yield ['data.object.id', 'evt'];

        yield ['data.object.ok', false];

        yield ['ratio', 1.5];

        yield ['missing', null];

        yield ['data.object', null];

        yield ['id.nested', null];
    }
}
