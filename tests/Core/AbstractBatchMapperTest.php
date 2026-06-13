<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Batch\BatchResult;
use IntegrationEngine\Core\Batch\BatchResultCollection;
use IntegrationEngine\Core\Exception\BatchMapperActionMismatchException;
use IntegrationEngine\Core\Response\EmptyResponse;
use IntegrationEngine\Tests\Fake\FakeBatchMapper;
use IntegrationEngine\Tests\Fake\FakePathAction;
use IntegrationEngine\Tests\Fake\FakeTokenAction;
use IntegrationEngine\Tests\Fake\FakeTokenResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AbstractBatchMapperTest extends TestCase
{
    protected function setUp(): void
    {
        FakeBatchMapper::$capturedCollection = null;
    }

    #[Test]
    public function mapDelegatesToConsolidateWhenAllItemsMatchAction(): void
    {
        $collection = new BatchResultCollection(
            results: ['a' => BatchResult::success(new EmptyResponse())],
            actionClasses: ['a' => FakePathAction::class],
        );

        $response = FakeBatchMapper::map($collection);

        self::assertSame($collection, FakeBatchMapper::$capturedCollection);
        self::assertInstanceOf(FakeTokenResponse::class, $response);
        self::assertSame(['count' => 1], $response->toArray());
    }

    #[Test]
    public function mapSkipsItemsWithNullActionClass(): void
    {
        // null actionClass means the item failed during preparation — not a mismatch
        $collection = new BatchResultCollection(
            results: ['a' => BatchResult::failure(new \RuntimeException('prep failed'))],
            actionClasses: [],
        );

        FakeBatchMapper::map($collection); // must not throw

        self::assertSame($collection, FakeBatchMapper::$capturedCollection);
    }

    #[Test]
    public function mapThrowsWhenAnyItemHasMismatchedActionClass(): void
    {
        $collection = new BatchResultCollection(
            results: [
                'a' => BatchResult::success(new EmptyResponse()),
                'b' => BatchResult::success(new EmptyResponse()),
            ],
            actionClasses: [
                'a' => FakePathAction::class,
                'b' => FakeTokenAction::class, // wrong action
            ],
        );

        $this->expectException(BatchMapperActionMismatchException::class);
        $this->expectExceptionMessage(\sprintf(
            'Batch mapper "%s" expects action "%s" but key "b" has action "%s".',
            FakeBatchMapper::class,
            FakePathAction::class,
            FakeTokenAction::class,
        ));

        FakeBatchMapper::map($collection);
    }

    #[Test]
    public function mapPassesFullCollectionIncludingFailuresToConsolidate(): void
    {
        $collection = new BatchResultCollection(
            results: [
                'ok' => BatchResult::success(new EmptyResponse()),
                'bad' => BatchResult::failure(new \RuntimeException('oops')),
            ],
            actionClasses: [
                'ok' => FakePathAction::class,
                // 'bad' has no recorded actionClass — treated as prep failure
            ],
        );

        FakeBatchMapper::map($collection);

        self::assertNotNull(FakeBatchMapper::$capturedCollection);
        self::assertCount(2, FakeBatchMapper::$capturedCollection);
        self::assertTrue(FakeBatchMapper::$capturedCollection->hasFailures());
    }

    #[Test]
    public function mapWithEmptyCollectionCallsConsolidate(): void
    {
        $collection = new BatchResultCollection([]);

        FakeBatchMapper::map($collection);

        self::assertSame($collection, FakeBatchMapper::$capturedCollection);
        self::assertCount(0, FakeBatchMapper::$capturedCollection);
    }
}
