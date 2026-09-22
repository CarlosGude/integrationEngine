<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Bundle\DependencyInjection;

use IntegrationEngine\Bundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class TransportConfigurationTest extends TestCase
{
    public function testTransportDefaultsAreOptIn(): void
    {
        $config = $this->process([]);
        self::assertNull($config['timeout']);
        self::assertNull($config['max_duration']);
        self::assertArrayNotHasKey('retry', $config);
        self::assertSame([], $config['allowed_hosts']);
        self::assertFalse($config['block_private_networks']);
    }

    public function testNullRetryEnablesDocumentedDefaults(): void
    {
        self::assertSame([
            'max_retries' => 3, 'delay_ms' => 200, 'multiplier' => 2.0,
            'max_delay_ms' => 2000, 'jitter' => 0.1,
            'status_codes' => [423, 425, 429, 500, 502, 503, 504, 507, 510],
            'retry_non_idempotent' => false,
        ], $this->process(['retry' => null])['retry']);
    }

    /** @param array<string, mixed> $options */
    #[DataProvider('provideInvalidTransportConfigurationIsRejectedCases')]
    public function testInvalidTransportConfigurationIsRejected(array $options): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process($options);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function provideInvalidTransportConfigurationIsRejectedCases(): iterable
    {
        yield 'negative timeout' => [['timeout' => -0.1]];

        yield 'negative duration' => [['max_duration' => -1]];

        yield 'zero retries' => [['retry' => ['max_retries' => 0]]];

        yield 'negative delay' => [['retry' => ['delay_ms' => -1]]];

        yield 'small multiplier' => [['retry' => ['multiplier' => 0.9]]];

        yield 'negative jitter' => [['retry' => ['jitter' => -0.1]]];

        yield 'large jitter' => [['retry' => ['jitter' => 1.1]]];

        yield 'invalid status' => [['retry' => ['status_codes' => [600]]]];

        yield 'scheme' => [['allowed_hosts' => ['https://example.com']]];

        yield 'path' => [['allowed_hosts' => ['example.com/path']]];

        yield 'misplaced wildcard' => [['allowed_hosts' => ['api.*.com']]];

        yield 'custom retry' => [['client_service' => 'custom', 'retry' => null]];

        yield 'custom timeout' => [['client_service' => 'custom', 'timeout' => 1.0]];

        yield 'custom duration' => [['client_service' => 'custom', 'max_duration' => 1.0]];

        yield 'custom network restriction' => [['client_service' => 'custom', 'block_private_networks' => true]];
    }

    /** @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function process(array $options): array
    {
        $result = (new Processor())->processConfiguration(new Configuration(), [
            ['integrations' => ['api' => $options + ['base_url' => 'https://example.com']]],
        ]);
        self::assertIsArray($result['integrations']);
        self::assertIsArray($result['integrations']['api']);

        /** @var array<string, mixed> $api */
        $api = $result['integrations']['api'];

        return $api;
    }
}
