<?php

declare(strict_types=1);
namespace IntegrationEngine\Tests\Infrastructure\Webhook;
use IntegrationEngine\Infrastructure\Webhook\DotPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
final class DotPathTest extends TestCase
{
    #[DataProvider('paths')]
    public function testResolvesOnlyScalarValues(string $path, string|int|float|bool|null $expected): void
    {
        self::assertSame($expected, DotPath::get(['id'=>123,'data'=>['object'=>['id'=>'evt','ok'=>false]],'ratio'=>1.5], $path));
    }
    /** @return iterable<array{string, string|int|float|bool|null}> */
    public static function paths(): iterable
    {
        yield ['id',123]; yield ['data.object.id','evt']; yield ['data.object.ok',false]; yield ['ratio',1.5]; yield ['missing',null]; yield ['data.object',null]; yield ['id.nested',null];
    }
}
