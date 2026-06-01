<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Bundle;

use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Core\Registry\IntegrationRegistry;
use IntegrationEngine\Tests\Bundle\Support\TestKernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class IntegrationEngineBundleTest extends TestCase
{
    private static string $fixtureYaml;

    public static function setUpBeforeClass(): void
    {
        self::$fixtureYaml = __DIR__.'/Support/fixtures/test_integration.yaml';
    }

    // ──────────────────────────────────────────────
    // Container compilation
    // ──────────────────────────────────────────────

    #[Test]
    public function containerCompilesWithValidConfig(): void
    {
        $kernel = $this->bootKernel([
            'integrations' => [
                'test_api' => [
                    'config_path' => self::$fixtureYaml,
                    'base_url' => 'https://example.com/api',
                ],
            ],
        ]);

        // If we got here the container compiled without errors
        $this->expectNotToPerformAssertions();

        $kernel->shutdown();
    }

    // ──────────────────────────────────────────────
    // CompilerPass wires the registry
    // ──────────────────────────────────────────────

    #[Test]
    public function registryHasIntegrationAfterBoot(): void
    {
        $kernel = $this->bootKernel([
            'integrations' => [
                'test_api' => [
                    'config_path' => self::$fixtureYaml,
                    'base_url' => 'https://example.com/api',
                ],
            ],
        ]);

        $registry = $kernel->getContainer()->get(IntegrationRegistry::class);

        self::assertInstanceOf(IntegrationRegistry::class, $registry);
        self::assertTrue($registry->has('test_api'));

        $kernel->shutdown();
    }

    #[Test]
    public function registryReturnsIntegrationEngine(): void
    {
        $kernel = $this->bootKernel([
            'integrations' => [
                'test_api' => [
                    'config_path' => self::$fixtureYaml,
                    'base_url' => 'https://example.com/api',
                ],
            ],
        ]);

        $registry = $kernel->getContainer()->get(IntegrationRegistry::class);

        self::assertInstanceOf(
            IntegrationEngine::class,
            $registry->get('test_api')
        );

        $kernel->shutdown();
    }

    // ──────────────────────────────────────────────
    // Multiple integrations
    // ──────────────────────────────────────────────

    #[Test]
    public function multipleIntegrationsAreAllRegistered(): void
    {
        $kernel = $this->bootKernel([
            'integrations' => [
                'first_api' => ['config_path' => self::$fixtureYaml, 'base_url' => 'https://first.example.com'],
                'second_api' => ['config_path' => self::$fixtureYaml, 'base_url' => 'https://second.example.com'],
            ],
        ]);

        $registry = $kernel->getContainer()->get(IntegrationRegistry::class);

        self::assertTrue($registry->has('first_api'));
        self::assertTrue($registry->has('second_api'));

        // Each integration gets its own isolated engine instance
        self::assertNotSame(
            $registry->get('first_api'),
            $registry->get('second_api')
        );

        $kernel->shutdown();
    }

    // ──────────────────────────────────────────────
    // Missing config_path
    // ──────────────────────────────────────────────

    #[Test]
    public function throwsWhenConfigPathDoesNotExist(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $kernel = $this->bootKernel([
            'integrations' => [
                'bad_api' => [
                    'config_path' => '/nonexistent/path/integration.yaml',
                    'base_url' => 'https://example.com/api',
                ],
            ],
        ]);

        // Trigger container instantiation
        $kernel->getContainer()->get(IntegrationRegistry::class);

        $kernel->shutdown();
    }

    // ──────────────────────────────────────────────
    // base_url or client_service required
    // ──────────────────────────────────────────────

    #[Test]
    public function throwsWhenNeitherBaseUrlNorClientServiceIsSet(): void
    {
        $this->expectException(\Exception::class);

        $this->bootKernel([
            'integrations' => [
                'bad_api' => [
                    'config_path' => self::$fixtureYaml,
                    // no base_url, no client_service
                ],
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    private function bootKernel(array $config): TestKernel
    {
        $kernel = new TestKernel($config);
        $kernel->boot();

        return $kernel;
    }
}
