<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core\Resilience;

use IntegrationEngine\Core\Exception\RequestResponseException;
use IntegrationEngine\Core\Resilience\EngineErrorClassifier;
use IntegrationEngine\Core\Resilience\ErrorClassification;
use IntegrationEngine\Core\Resilience\ErrorClassifierInterface;
use IntegrationEngine\Core\Resilience\ExponentialBackoff;
use IntegrationEngine\Core\Resilience\ExponentialBackoffPolicy;
use IntegrationEngine\Infrastructure\Resilience\SymfonyErrorClassifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;

final class ResilienceBoundaryTest extends TestCase
{
    public function testCoreClassifiesEngineErrorsWithoutAssumingNetworkFailure(): void
    {
        $classifier = new EngineErrorClassifier();
        self::assertSame(503, $classifier->classify(new RequestResponseException(503, 'failure'))->statusCode);
        self::assertTrue($classifier->classify(new RequestResponseException(503, 'failure'))->isTransient());
        self::assertFalse($classifier->classify(new RequestResponseException(0, 'local failure'))->isTransient());
        self::assertNull($classifier->classify(new \RuntimeException('local', 503))->statusCode);
        self::assertFalse($classifier->classify(new \RuntimeException('local', 503))->isTransient());
    }

    public function testCorePolicyCanUseAnApplicationClassifier(): void
    {
        $classifier = new class implements ErrorClassifierInterface {
            public function classify(\Throwable $error): ErrorClassification
            {
                return new ErrorClassification(statusCode: 429);
            }
        };
        $error = new \RuntimeException('application-specific failure');
        self::assertFalse((new ExponentialBackoff())->shouldRetry($error, 1));
        self::assertTrue((new ExponentialBackoff(classifier: $classifier))->shouldRetry($error, 1));
        self::assertFalse((new ExponentialBackoff(maxAttempts: 1, classifier: $classifier))->shouldRetry($error, 2));
    }

    public function testSymfonyClassificationIsExplicitForTheNewCorePolicy(): void
    {
        $error = new TransportException('network');
        self::assertFalse((new ExponentialBackoff())->shouldRetry($error, 1));
        self::assertTrue((new ExponentialBackoff(classifier: new SymfonyErrorClassifier()))->shouldRetry($error, 1));
        self::assertTrue((new ExponentialBackoffPolicy())->shouldRetry($error, 1));
    }
}
