<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Bundle\Command;

use IntegrationEngine\Bundle\Command\MakeIntegrationCommand;
use IntegrationEngine\Bundle\Generator\IntegrationFileGenerator;
use IntegrationEngine\Infrastructure\Http\ClientAdapterResolver;
use IntegrationEngine\Infrastructure\Http\GraphQLClientAdapter;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class MakeIntegrationCommandTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/ie_command_'.uniqid();
        mkdir($this->projectDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->projectDir);
    }

    #[Test]
    public function firstRunCreatesBundleConfigAndFullSkeleton(): void
    {
        $tester = $this->tester();

        // First run asks: base URL, client type, action path, HTTP method.
        $tester->setInputs(['https://api.example.com', 'rest', '/employees', 'GET']);
        $exitCode = $tester->execute(['name' => 'MyApi', 'action' => 'GetEmployees']);

        self::assertSame(Command::SUCCESS, $exitCode);

        $root = $this->projectDir.'/src/Infrastructure/Integrations/MyApi';
        self::assertFileExists($root.'/MyApiIntegration.php');
        self::assertFileExists($root.'/GetEmployees/Request/GetEmployeesAction.php');
        self::assertFileExists($root.'/GetEmployees/Response/GetEmployeesMapper.php');
        self::assertFileExists($root.'/GetEmployees/Response/GetEmployeesResponse.php');

        $integrationYaml = (string) file_get_contents($root.'/MyApi.yaml');
        self::assertStringContainsString('GetEmployees:', $integrationYaml);
        self::assertStringContainsString('method: GET', $integrationYaml);
        self::assertStringContainsString('path: /employees', $integrationYaml);

        $bundleConfig = (string) file_get_contents($this->projectDir.'/config/packages/integration_engine.yaml');
        self::assertStringContainsString('my_api:', $bundleConfig);
        self::assertStringContainsString("base_url: 'https://api.example.com'", $bundleConfig);
    }

    #[Test]
    public function secondRunAddsActionWithoutAskingForBaseUrl(): void
    {
        $this->writeBundleConfig('my_api');
        // Integration dir exists: the integration class must not be regenerated.
        mkdir($this->projectDir.'/src/Infrastructure/Integrations/MyApi', 0o755, true);

        $tester = $this->tester();
        $tester->setInputs(['/orders/{id}', 'GET']);
        $exitCode = $tester->execute(['name' => 'MyApi', 'action' => 'GetOrder']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringNotContainsString('Base URL', $tester->getDisplay());

        $root = $this->projectDir.'/src/Infrastructure/Integrations/MyApi';
        self::assertFileExists($root.'/GetOrder/Request/GetOrderAction.php');
        self::assertFileDoesNotExist($root.'/MyApiIntegration.php');
    }

    #[Test]
    public function deleteActionGeneratesNoResponseLayer(): void
    {
        $this->writeBundleConfig('my_api');

        $tester = $this->tester();
        $tester->setInputs(['/employees/{id}', 'DELETE']);
        $exitCode = $tester->execute(['name' => 'MyApi', 'action' => 'DeleteEmployee']);

        self::assertSame(Command::SUCCESS, $exitCode);

        $root = $this->projectDir.'/src/Infrastructure/Integrations/MyApi';
        self::assertFileExists($root.'/DeleteEmployee/Request/DeleteEmployeeAction.php');
        self::assertDirectoryDoesNotExist($root.'/DeleteEmployee/Response');

        $action = (string) file_get_contents($root.'/DeleteEmployee/Request/DeleteEmployeeAction.php');
        self::assertStringContainsString('return false;', $action);
        self::assertStringContainsString('return null;', $action);
    }

    #[Test]
    public function graphqlIntegrationSkipsPathAndMethodQuestions(): void
    {
        $this->writeBundleConfig('my_api', client: 'graphql');

        $tester = $this->tester();
        // No inputs: graphql adapter needs neither path nor method.
        $exitCode = $tester->execute(['name' => 'MyApi', 'action' => 'GetCharacters']);

        self::assertSame(Command::SUCCESS, $exitCode);

        $display = $tester->getDisplay();
        self::assertStringNotContainsString('Path for action', $display);
        self::assertStringNotContainsString('HTTP method', $display);

        $root = $this->projectDir.'/src/Infrastructure/Integrations/MyApi';
        $yaml = (string) file_get_contents($root.'/MyApi.yaml');
        self::assertStringContainsString('GetCharacters:', $yaml);
        self::assertStringNotContainsString('method:', $yaml);
        self::assertStringNotContainsString('path:', $yaml);

        $action = (string) file_get_contents($root.'/GetCharacters/Request/GetCharactersAction.php');
        self::assertStringContainsString('GraphQLBodyInterface', $action);
    }

    #[Test]
    public function existingFilesAreSkippedWithoutForce(): void
    {
        $this->writeBundleConfig('my_api');

        $tester = $this->tester();
        $tester->setInputs(['/employees', 'GET']);
        $tester->execute(['name' => 'MyApi', 'action' => 'GetEmployees']);

        $actionFile = $this->projectDir.'/src/Infrastructure/Integrations/MyApi/GetEmployees/Request/GetEmployeesAction.php';
        file_put_contents($actionFile, '<?php // manually edited');

        $tester = $this->tester();
        $tester->setInputs(['/employees', 'GET']);
        $tester->execute(['name' => 'MyApi', 'action' => 'GetEmployees']);

        self::assertStringContainsString('Skipped', $tester->getDisplay());
        self::assertSame('<?php // manually edited', file_get_contents($actionFile));
    }

    #[Test]
    public function unknownClientTypeInBundleConfigFails(): void
    {
        $this->writeBundleConfig('my_api', client: 'soap');

        $tester = $this->tester();
        $exitCode = $tester->execute(['name' => 'MyApi', 'action' => 'GetEmployees']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Unknown client type "soap"', $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        $resolver = new ClientAdapterResolver();
        $resolver->register(SymfonyHttpClientAdapter::getClientType(), SymfonyHttpClientAdapter::class);
        $resolver->register(GraphQLClientAdapter::getClientType(), GraphQLClientAdapter::class);

        return new CommandTester(new MakeIntegrationCommand(
            $this->projectDir,
            new IntegrationFileGenerator(),
            $resolver,
        ));
    }

    private function writeBundleConfig(string $snakeName, string $client = 'rest'): void
    {
        $dir = $this->projectDir.'/config/packages';
        mkdir($dir, 0o755, true);

        $clientLine = 'rest' !== $client ? "\n            client: {$client}" : '';

        file_put_contents($dir.'/integration_engine.yaml', <<<YAML
            integration_engine:
                integrations:
                    {$snakeName}:
                        base_url: 'https://api.example.com'{$clientLine}
                        config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/MyApi/MyApi.yaml'
            YAML);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
