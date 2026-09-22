<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core\Resilience;

use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Core\Resilience\ExponentialBackoffPolicy;
use IntegrationEngine\Tests\Fake\FakeProtectedAction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExponentialBackoffPolicyTest extends TestCase
{
    #[Test]
    public function allowsExactlyThreeRetriesByDefaultWithIncreasingDelays(): void
    {
        $policy = new ExponentialBackoffPolicy();
        $error = new RequestResponseException(503, 'Unavailable');

        self::assertSame('exponential_backoff', $policy->getName());
        self::assertSame(3, $policy->getMaxAttempts());
        foreach ([1 => 100, 2 => 200, 3 => 400] as $attempt => $delay) {
            self::assertTrue($policy->shouldRetry($error, $attempt));
            self::assertSame($delay, $policy->getBackoffMs($attempt));
        }
        self::assertFalse($policy->shouldRetry($error, 4));
        self::assertFalse($policy->shouldRetry($error, 5));
    }

    #[Test]
    public function customLimitAndDelayAreHonoured(): void
    {
        $policy = new ExponentialBackoffPolicy(1, 25);
        $error = new RequestResponseException(429, 'Rate limit');

        self::assertSame(1, $policy->getMaxAttempts());
        self::assertTrue($policy->shouldRetry($error, 1));
        self::assertFalse($policy->shouldRetry($error, 2));
        self::assertSame(25, $policy->getBackoffMs(1));
        self::assertSame(50, $policy->getBackoffMs(2));
    }

    #[Test]
    public function zeroDelayIsAllowed(): void
    {
        $policy = new ExponentialBackoffPolicy(initialBackoffMs: 0);

        self::assertSame(0, $policy->getBackoffMs(1));
        self::assertSame(0, $policy->getBackoffMs(3));
        self::assertSame(0, $policy->getBackoffMs(PHP_INT_MAX));
    }

    #[Test]
    public function supportsTheLargestRepresentableDelays(): void
    {
        self::assertSame(PHP_INT_MAX, (new ExponentialBackoffPolicy(initialBackoffMs: PHP_INT_MAX))->getBackoffMs(1));
        self::assertSame(1 << (\PHP_INT_SIZE * 8 - 2), (new ExponentialBackoffPolicy(initialBackoffMs: 1))->getBackoffMs(\PHP_INT_SIZE * 8 - 1));
    }

    #[Test]
    #[DataProvider('provideRejectsOverflowInsteadOfReturningNegativeOrZeroDelaysCases')]
    public function rejectsOverflowInsteadOfReturningNegativeOrZeroDelays(int $initialDelay, int $attempt): void
    {
        $this->expectException(\OverflowException::class);
        $this->expectExceptionMessage('Backoff delay exceeds the integer range.');

        (new ExponentialBackoffPolicy(initialBackoffMs: $initialDelay))->getBackoffMs($attempt);
    }

    /** @return iterable<array{int, int}> */
    public static function provideRejectsOverflowInsteadOfReturningNegativeOrZeroDelaysCases(): iterable
    {
        yield [PHP_INT_MAX, 2];

        yield [1, \PHP_INT_SIZE * 8];

        yield [1, \PHP_INT_SIZE * 8 + 1];

        yield [1, PHP_INT_MAX];
    }

    #[Test]
    public function permanentAndUnknownErrorsAreNotRetried(): void
    {
        $policy = new ExponentialBackoffPolicy();

        self::assertFalse($policy->shouldRetry(new RequestResponseException(401, 'Unauthorized'), 1));
        self::assertFalse($policy->shouldRetry(new \LogicException('Invalid action'), 1));
    }

    #[Test]
    #[DataProvider('provideRejectsInvalidConfigurationCases')]
    public function rejectsInvalidConfiguration(int $maxAttempts, int $delay, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new ExponentialBackoffPolicy($maxAttempts, $delay);
    }

    /** @return iterable<array{int, int, string}> */
    public static function provideRejectsInvalidConfigurationCases(): iterable
    {
        yield [0, 100, 'maxAttempts must be >= 1'];

        yield [-1, 100, 'maxAttempts must be >= 1'];

        yield [3, -1, 'initialBackoffMs must be >= 0'];
    }

    #[Test]
    #[DataProvider('provideRejectsInvalidRetryNumbersCases')]
    public function rejectsInvalidRetryNumbers(int $attempt, bool $calculateDelay): void
    {
        $policy = new ExponentialBackoffPolicy();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('attempt must be >= 1');

        if ($calculateDelay) {
            $policy->getBackoffMs($attempt);
        } else {
            $policy->shouldRetry(new RequestResponseException(503, 'Unavailable'), $attempt);
        }
    }

    /** @return iterable<array{int, bool}> */
    public static function provideRejectsInvalidRetryNumbersCases(): iterable
    {
        yield [0, true];

        yield [-1, true];

        yield [0, false];

        yield [-1, false];
    }

    #[Test]
    public function fallbackRethrowsTheOriginalFailure(): void
    {
        $error = new RequestResponseException(503, 'Unavailable');

        try {
            (new ExponentialBackoffPolicy())->getFallback(FakeProtectedAction::create('GET', '/'), $error);
            self::fail('The fallback must throw the original failure.');
        } catch (RequestResponseException $caught) {
            self::assertSame($error, $caught);
        }
    }
}
