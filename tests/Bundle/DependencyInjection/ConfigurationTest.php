<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Bundle\DependencyInjection;

use IntegrationEngine\Bundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    #[Test]
    public function integrationsDefaultToEmptyArray(): void
    {
        $config = $this->process([]);

        self::assertSame([], $config['integrations']);
    }

    #[Test]
    public function integrationDefaultsAreApplied(): void
    {
        $config = $this->process([
            'integrations' => [
                'my_api' => ['base_url' => 'https://api.example.com'],
            ],
        ]);

        $integration = $config['integrations']['my_api'];

        self::assertSame('https://api.example.com', $integration['base_url']);
        self::assertSame('rest', $integration['client']);
        self::assertNull($integration['config_path']);
        self::assertNull($integration['client_service']);
        self::assertNull($integration['cache_service']);
        self::assertSame([], $integration['headers']);
    }

    #[Test]
    public function integrationWithoutBaseUrlOrClientServiceIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/must define either "base_url" or "client_service"/');

        $this->process([
            'integrations' => [
                'my_api' => ['config_path' => '/some/path.yaml'],
            ],
        ]);
    }

    #[Test]
    public function clientServiceAloneSatisfiesValidation(): void
    {
        $config = $this->process([
            'integrations' => [
                'my_api' => ['client_service' => 'app.custom_client'],
            ],
        ]);

        self::assertSame('app.custom_client', $config['integrations']['my_api']['client_service']);
        self::assertNull($config['integrations']['my_api']['base_url']);
    }

    #[Test]
    public function emptyClientTypeIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/Client type cannot be empty/');

        $this->process([
            'integrations' => [
                'my_api' => ['base_url' => 'https://api.example.com', 'client' => '  '],
            ],
        ]);
    }

    #[Test]
    public function headersArePreserved(): void
    {
        $config = $this->process([
            'integrations' => [
                'my_api' => [
                    'base_url' => 'https://api.example.com',
                    'headers' => ['X-Tenant' => 'acme', 'Accept-Language' => 'es'],
                ],
            ],
        ]);

        self::assertSame(
            ['X-Tenant' => 'acme', 'Accept-Language' => 'es'],
            $config['integrations']['my_api']['headers']
        );
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{integrations: array<string, array<string, mixed>>}
     */
    private function process(array $config): array
    {
        // @phpstan-ignore-next-line
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
