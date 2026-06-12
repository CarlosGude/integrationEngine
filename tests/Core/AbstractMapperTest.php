<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Exception\MapperActionMismatchException;
use IntegrationEngine\Tests\Fake\FakePathAction;
use IntegrationEngine\Tests\Fake\FakeTokenAction;
use IntegrationEngine\Tests\Fake\FakeTokenMapper;
use IntegrationEngine\Tests\Fake\FakeTokenResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The engine fail-fasts on mapper/action mismatch before calling map(),
 * so map()'s own guard is only reachable by calling it directly — it exists
 * as a public contract for callers outside the engine flow.
 */
final class AbstractMapperTest extends TestCase
{
    #[Test]
    public function mapDelegatesToTransformWhenActionMatches(): void
    {
        $action = FakeTokenAction::create('GET', '/token');

        $response = FakeTokenMapper::map($action, ['access_token' => 'abc']);

        self::assertInstanceOf(FakeTokenResponse::class, $response);
        self::assertSame(['access_token' => 'abc'], $response->toArray());
    }

    #[Test]
    public function mapThrowsWhenActionDoesNotMatchMapper(): void
    {
        $action = FakePathAction::create('GET', '/orders');

        $this->expectException(MapperActionMismatchException::class);
        $this->expectExceptionMessage(\sprintf(
            'Mapper "%s" expects action "%s" but received "%s".',
            FakeTokenMapper::class,
            FakeTokenAction::class,
            FakePathAction::class,
        ));

        FakeTokenMapper::map($action, []);
    }
}
