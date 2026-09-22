<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Infrastructure\Http\RetryStrategyFactory;
use PHPUnit\Framework\TestCase;

final class RetryStrategyFactoryTest extends TestCase
{
    public function testDefaultStrategyRestrictsEveryStatusIncludingTransportErrors(): void
    {
        $strategy = RetryStrategyFactory::create([]);
        $codes = (new \ReflectionProperty($strategy, 'statusCodes'))->getValue($strategy);
        self::assertIsArray($codes);
        self::assertSame([0, 423, 425, 429, 500, 502, 503, 504, 507, 510], array_keys($codes));
        foreach (['delayMs' => 200, 'multiplier' => 2.0, 'maxDelayMs' => 2000, 'jitter' => 0.1] as $property => $expected) {
            self::assertSame($expected, (new \ReflectionProperty($strategy, $property))->getValue($strategy));
        }
        foreach ($codes as $methods) {
            self::assertSame(['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS', 'TRACE'], $methods);
        }
    }

    public function testOptInAndDelayConfigurationAreForwarded(): void
    {
        $strategy = RetryStrategyFactory::create(['retry_non_idempotent' => true, 'status_codes' => [503], 'delay_ms' => 123, 'multiplier' => 3.0, 'max_delay_ms' => 999, 'jitter' => 0.5]);
        foreach (['statusCodes' => [0, 503], 'delayMs' => 123, 'multiplier' => 3.0, 'maxDelayMs' => 999, 'jitter' => 0.5] as $property => $expected) {
            self::assertSame($expected, (new \ReflectionProperty($strategy, $property))->getValue($strategy));
        }
    }
}
