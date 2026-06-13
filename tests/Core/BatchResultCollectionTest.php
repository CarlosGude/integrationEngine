<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Batch\BatchResult;
use IntegrationEngine\Core\Batch\BatchResultCollection;
use IntegrationEngine\Core\Response\EmptyResponse;
use IntegrationEngine\Tests\Fake\FakeBatchMapper;
use IntegrationEngine\Tests\Fake\FakePathAction;
use IntegrationEngine\Tests\Fake\FakeTokenResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BatchResultCollectionTest extends TestCase
{
    #[Test]
    public function emptyCollectionHasNoFailuresAndCountsZero(): void
    {
        $collection = new BatchResultCollection([]);

        self::assertCount(0, $collection);
        self::assertFalse($collection->hasFailures());
        self::assertSame([], $collection->keys());
        self::assertSame([], $collection->responses());
        self::assertSame([], $collection->errors());
    }

    #[Test]
    public function keysReturnsInputKeysInOrder(): void
    {
        $collection = new BatchResultCollection([
            'b' => BatchResult::success(new EmptyResponse()),
            'a' => BatchResult::success(new EmptyResponse()),
        ]);

        self::assertSame(['b', 'a'], $collection->keys());
    }

    #[Test]
    public function iterationPreservesKeysAndValues(): void
    {
        $first = BatchResult::success(new EmptyResponse());
        $second = BatchResult::failure(new \RuntimeException('boom'));

        $collection = new BatchResultCollection(['x' => $first, 'y' => $second]);

        $seen = [];
        foreach ($collection as $key => $result) {
            $seen[$key] = $result;
        }

        self::assertSame(['x' => $first, 'y' => $second], $seen);
    }

    #[Test]
    public function arrayAccessReturnsCorrectResult(): void
    {
        $result = BatchResult::success(new EmptyResponse());
        $collection = new BatchResultCollection(['k' => $result]);

        self::assertTrue(isset($collection['k']));
        self::assertSame($result, $collection['k']);
        self::assertFalse(isset($collection['missing']));
    }

    #[Test]
    public function arrayAccessGetThrowsForUnknownKey(): void
    {
        $collection = new BatchResultCollection([]);

        $this->expectException(\OutOfBoundsException::class);
        $collection['missing']; // @phpstan-ignore expr.resultUnused
    }

    #[Test]
    public function arrayAccessSetIsNotAllowed(): void
    {
        $collection = new BatchResultCollection([]);

        $this->expectException(\LogicException::class);
        $collection['k'] = BatchResult::success(new EmptyResponse());
    }

    #[Test]
    public function arrayAccessUnsetIsNotAllowed(): void
    {
        $result = BatchResult::success(new EmptyResponse());
        $collection = new BatchResultCollection(['k' => $result]);

        $this->expectException(\LogicException::class);
        unset($collection['k']);
    }

    #[Test]
    public function hasFailuresReturnsTrueWhenAnyItemFailed(): void
    {
        $collection = new BatchResultCollection([
            'ok' => BatchResult::success(new EmptyResponse()),
            'bad' => BatchResult::failure(new \RuntimeException()),
        ]);

        self::assertTrue($collection->hasFailures());
    }

    #[Test]
    public function hasFailuresReturnsFalseWhenAllSucceeded(): void
    {
        $collection = new BatchResultCollection([
            'a' => BatchResult::success(new EmptyResponse()),
            'b' => BatchResult::success(new EmptyResponse()),
        ]);

        self::assertFalse($collection->hasFailures());
    }

    #[Test]
    public function responsesReturnsOnlySuccessfulItemsKeyed(): void
    {
        $r1 = new EmptyResponse();
        $r2 = new EmptyResponse();
        $r3 = new EmptyResponse();
        $collection = new BatchResultCollection([
            'a' => BatchResult::success($r1),
            'bad' => BatchResult::failure(new \RuntimeException()),
            'b' => BatchResult::success($r2),
            'c' => BatchResult::success($r3),
        ]);

        self::assertSame(['a' => $r1, 'b' => $r2, 'c' => $r3], $collection->responses());
    }

    #[Test]
    public function errorsReturnsOnlyFailedItemsKeyed(): void
    {
        $e1 = new \RuntimeException('one');
        $e2 = new \RuntimeException('two');
        $e3 = new \RuntimeException('three');
        $collection = new BatchResultCollection([
            'ok' => BatchResult::success(new EmptyResponse()),
            'x' => BatchResult::failure($e1),
            'y' => BatchResult::failure($e2),
            'z' => BatchResult::failure($e3),
        ]);

        self::assertSame(['x' => $e1, 'y' => $e2, 'z' => $e3], $collection->errors());
    }

    #[Test]
    public function actionClassForReturnsClassWhenPresent(): void
    {
        $collection = new BatchResultCollection(
            results: ['k' => BatchResult::success(new EmptyResponse())],
            actionClasses: ['k' => \stdClass::class],
        );

        self::assertSame(\stdClass::class, $collection->actionClassFor('k'));
    }

    #[Test]
    public function actionClassForReturnsNullWhenAbsent(): void
    {
        $collection = new BatchResultCollection(
            results: ['k' => BatchResult::failure(new \RuntimeException())],
        );

        self::assertNull($collection->actionClassFor('k'));
    }

    // ── mapWith() ─────────────────────────────────────────────────────────────

    #[Test]
    public function mapWithDelegatesToBatchMapper(): void
    {
        FakeBatchMapper::$capturedCollection = null;

        $collection = new BatchResultCollection(
            results: ['k' => BatchResult::success(new EmptyResponse())],
            actionClasses: ['k' => FakePathAction::class],
        );

        $response = $collection->mapWith(FakeBatchMapper::class);

        self::assertInstanceOf(FakeTokenResponse::class, $response);
        self::assertSame($collection, FakeBatchMapper::$capturedCollection);
    }

    #[Test]
    public function mapWithThrowsWhenClassIsNotAnAbstractBatchMapper(): void
    {
        $collection = new BatchResultCollection([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\stdClass::class);

        $collection->mapWith(\stdClass::class);
    }
}
