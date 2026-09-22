<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Bundle\Command;

use IntegrationEngine\Bundle\Command\DebugIntegrationCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DebugIntegrationCommandTest extends TestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'integration-inspection-');
        self::assertIsString($path);
        $this->configPath = $path;
    }

    protected function tearDown(): void
    {
        unlink($this->configPath);
    }

    #[Test]
    public function listsIntegrationsWithoutReadingActionFilesOrExposingCredentials(): void
    {
        $tester = new CommandTester(new DebugIntegrationCommand([
            'orders' => [
                'client' => 'rest', 'client_service' => null, 'config_path' => '/missing/orders.yaml',
                'base_url' => 'https://user:password@example.com', 'headers' => ['Authorization' => 'secret'],
            ],
            'custom' => ['client' => 'rest', 'client_service' => 'app.custom_client', 'config_path' => null],
        ]));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('orders', $display);
        self::assertStringContainsString('rest', $display);
        self::assertStringContainsString('/missing/orders.yaml', $display);
        self::assertStringContainsString('app.custom_client', $display);
        self::assertStringNotContainsString('password', $display);
        self::assertStringNotContainsString('secret', $display);

        self::assertSame(Command::SUCCESS, $tester->execute(['--format' => 'json']));
        self::assertSame(['integrations' => [
            ['name' => 'orders', 'client' => 'rest', 'config_path' => '/missing/orders.yaml'],
            ['name' => 'custom', 'client' => 'app.custom_client', 'config_path' => ''],
        ]], json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function inspectsDeclarativeActionsWithoutLoadingClassesOrResolvingPaths(): void
    {
        file_put_contents($this->configPath, <<<'YAML'
get_order:
    action: App\UnloadedAction
    method: GET
    path: /orders/{id}
    body: App\RequiredBody
    authorization:
        type: bearer
        token: secret-action-token
webhooks:
    created:
        mapper: App\WebhookMapper
        signature:
            secret: hidden-webhook-secret
create_order:
    action: App\UnloadedCreateAction
YAML);
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['integration' => 'orders', '--format' => 'json']));
        self::assertSame([
            'integration' => 'orders',
            'client' => 'rest',
            'actions' => [
                ['name' => 'get_order', 'method' => 'GET', 'path' => '/orders/{id}', 'class' => 'App\UnloadedAction'],
                ['name' => 'create_order', 'method' => 'POST', 'path' => '/', 'class' => 'App\UnloadedCreateAction'],
            ],
        ], json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR));

        self::assertSame(Command::SUCCESS, $tester->execute(['integration' => 'orders']));
        self::assertStringContainsString('/orders/{id}', $tester->getDisplay());
        self::assertStringContainsString('App\UnloadedAction', $tester->getDisplay());
        self::assertStringNotContainsString('secret-action-token', $tester->getDisplay());
        self::assertStringNotContainsString('hidden-webhook-secret', $tester->getDisplay());
    }

    #[Test]
    public function handlesEmptyConfiguration(): void
    {
        $tester = new CommandTester(new DebugIntegrationCommand([]));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('No integrations configured.', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute(['--format' => 'json']));
        self::assertSame(['integrations' => []], json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR));

        file_put_contents($this->configPath, 'webhooks: {}');
        $tester = $this->tester();
        self::assertSame(Command::SUCCESS, $tester->execute(['integration' => 'orders']));
        self::assertStringContainsString('No actions configured.', $tester->getDisplay());
    }

    #[Test]
    public function reportsUnknownIntegrationsAndUnsupportedFormats(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['integration' => 'missing']));
        self::assertStringContainsString('Unknown integration: missing', $tester->getDisplay());
        self::assertSame(Command::INVALID, $tester->execute(['--format' => 'xml']));
        self::assertStringContainsString('Output format must be', $tester->getDisplay());
    }

    #[Test]
    #[DataProvider('provideReportsInvalidYamlWithoutPrintingItsContentsCases')]
    public function reportsInvalidYamlWithoutPrintingItsContents(string $yaml): void
    {
        file_put_contents($this->configPath, $yaml);
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['integration' => 'orders']));
        self::assertStringContainsString('Unable to inspect actions', $tester->getDisplay());
        self::assertStringNotContainsString('secret-token', $tester->getDisplay());
    }

    /** @return iterable<array{string}> */
    public static function provideReportsInvalidYamlWithoutPrintingItsContentsCases(): iterable
    {
        yield [''];

        yield ['secret-token'];

        yield ['action: [secret-token'];

        yield ['action: secret-token'];

        yield ['action: {path: secret-token}'];

        yield ['action: {action: 42}'];

        yield ['action: {action: App\Action, method: [secret-token]}'];

        yield ['action: {action: App\Action, path: [secret-token]}'];

        yield ['- {action: App\Action}'];
    }

    #[Test]
    public function reportsMissingConfigFiles(): void
    {
        foreach ([null, $this->configPath.'.missing'] as $path) {
            $tester = new CommandTester(new DebugIntegrationCommand([
                'orders' => ['client' => 'rest', 'client_service' => null, 'config_path' => $path],
            ]));

            self::assertSame(Command::FAILURE, $tester->execute(['integration' => 'orders']));
            self::assertStringContainsString('Unable to inspect actions', $tester->getDisplay());
        }
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new DebugIntegrationCommand([
            'orders' => ['client' => 'rest', 'client_service' => null, 'config_path' => $this->configPath],
        ]));
    }
}
