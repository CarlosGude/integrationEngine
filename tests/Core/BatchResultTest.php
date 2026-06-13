<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Batch\BatchResult;
use IntegrationEngine\Core\Response\EmptyResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BatchResultTest extends TestCase
{
    #[Test]
    public function successExposesResponseAndNoError(): void
    {
        $response = new EmptyResponse();

        $result = BatchResult::success($response);

        self::assertTrue($result->isSuccess());
        self::assertSame($response, $result->response());
        self::assertNull($result->error());
    }

    #[Test]
    public function failureExposesErrorAndIsNotSuccess(): void
    {
        $error = new \RuntimeException('boom');

        $result = BatchResult::failure($error);

        self::assertFalse($result->isSuccess());
        self::assertSame($error, $result->error());
    }

    #[Test]
    public function responseRethrowsTheStoredFailure(): void
    {
        $error = new \RuntimeException('boom');
        $result = BatchResult::failure($error);

        try {
            $result->response();
            self::fail('Expected the stored failure to be rethrown.');
        } catch (\RuntimeException $caught) {
            self::assertSame($error, $caught);
        }
    }
}
