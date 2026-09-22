<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core\Resilience;

use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Core\Resilience\ErrorClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ErrorClassifierTest extends TestCase
{
    #[Test]
    #[DataProvider('provideClassifiesEngineHttpErrorsCases')]
    public function classifiesEngineHttpErrors(int $status, bool $transient, bool $permanent): void
    {
        $error = new RequestResponseException($status, 'Upstream failure');

        self::assertSame($status, ErrorClassifier::getStatusCode($error));
        self::assertSame($transient, ErrorClassifier::isTransient($error));
        self::assertSame($permanent, ErrorClassifier::isPermanent($error));
    }

    /** @return iterable<string, array{int, bool, bool}> */
    public static function provideClassifiesEngineHttpErrorsCases(): iterable
    {
        yield 'unknown transport or local validation' => [0, false, false];

        yield 'GraphQL application error' => [200, false, false];

        yield 'redirect' => [399, false, false];

        yield 'bad request' => [400, false, true];

        yield 'unauthorized' => [401, false, true];

        yield 'forbidden' => [403, false, true];

        yield 'not found' => [404, false, true];

        yield 'request timeout' => [408, true, false];

        yield 'rate limited' => [429, true, false];

        yield 'last client status' => [499, false, true];

        yield 'server error' => [500, true, false];

        yield 'unavailable' => [503, true, false];

        yield 'gateway timeout' => [504, true, false];

        yield 'last server status' => [599, true, false];

        yield 'outside HTTP error range' => [600, false, false];
    }

    #[Test]
    #[DataProvider('provideStillClassifiesSymfonyHttpExceptionsCases')]
    public function stillClassifiesSymfonyHttpExceptions(int $status, bool $transient, bool $permanent): void
    {
        $response = (new MockHttpClient(new MockResponse('{}', ['http_code' => $status])))->request('GET', 'https://example.com');
        $error = $status >= 500 ? new ServerException($response) : new ClientException($response);

        self::assertSame($status, ErrorClassifier::getStatusCode($error));
        self::assertSame($transient, ErrorClassifier::isTransient($error));
        self::assertSame($permanent, ErrorClassifier::isPermanent($error));
    }

    /** @return iterable<array{int, bool, bool}> */
    public static function provideStillClassifiesSymfonyHttpExceptionsCases(): iterable
    {
        yield [401, false, true];

        yield [408, true, false];

        yield [429, true, false];

        yield [503, true, false];
    }

    #[Test]
    public function transportFailuresAreTransientWithoutAnHttpStatus(): void
    {
        $error = new TransportException('Connection refused');

        self::assertNull(ErrorClassifier::getStatusCode($error));
        self::assertTrue(ErrorClassifier::isTransient($error));
        self::assertFalse(ErrorClassifier::isPermanent($error));
    }

    #[Test]
    public function arbitraryExceptionCodesAreNotHttpStatuses(): void
    {
        $error = new \RuntimeException('Application error', 503);

        self::assertNull(ErrorClassifier::getStatusCode($error));
        self::assertFalse(ErrorClassifier::isTransient($error));
        self::assertFalse(ErrorClassifier::isPermanent($error));
    }
}
