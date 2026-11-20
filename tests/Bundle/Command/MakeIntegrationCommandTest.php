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
use Symfony\Component\Yaml\Yaml;

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
    public function interactiveActionNameIsUsedWhenArgumentOmitted(): void
    {
        $this->writeBundleConfig('my_api');

        $tester = $this->tester();
        $tester->setInputs(['GetEmployees', '/employees', 'GET']);
        $exitCode = $tester->execute(['name' => 'MyApi']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Name of the first action', $tester->getDisplay());

        $root = $this->projectDir.'/src/Infrastructure/Integrations/MyApi';
        self::assertFileExists($root.'/GetEmployees/Request/GetEmployeesAction.php');
    }

    #[Test]
    public function interactiveActionNamePromptRejectsEmptyInputBeforeAccepting(): void
    {
        $this->writeBundleConfig('my_api');

        $tester = $this->tester();
        $tester->setInputs(['', 'GetEmployees', '/employees', 'GET']);
        $exitCode = $tester->execute(['name' => 'MyApi']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Action name cannot be empty.', $tester->getDisplay());

        $root = $this->projectDir.'/src/Infrastructure/Integrations/MyApi';
        self::assertFileExists($root.'/GetEmployees/Request/GetEmployeesAction.php');
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
    public function rerunningForAnExistingActionDoesNotDuplicateItsYamlEntry(): void
    {
        $this->writeBundleConfig('my_api');

        foreach ([1, 2] as $run) {
            $tester = $this->tester();
            $tester->setInputs(['/employees', 'GET']);
            $tester->execute(['name' => 'MyApi', 'action' => 'GetEmployees', '--force' => true]);
        }

        $yamlPath = $this->projectDir.'/src/Infrastructure/Integrations/MyApi/MyApi.yaml';
        self::assertSame(1, substr_count((string) file_get_contents($yamlPath), 'GetEmployees:'));
        self::assertStringContainsString('"GetEmployees" is already declared', $tester->getDisplay());
        $actions = Yaml::parseFile($yamlPath);
        self::assertIsArray($actions);
        self::assertArrayHasKey('GetEmployees', $actions);
    }

    /**
     * The CI job that validates the generator on PHP 8.2 (see
     * .github/workflows/ci.yml) runs this command with --no-interaction
     * against a bare project with no bundle config yet — every question
     * must fall back to its default instead of blocking on input.
     */
    #[Test]
    public function noInteractionFirstRunFallsBackToDefaultsAndSucceeds(): void
    {
        $tester = $this->tester();

        $exitCode = $tester->execute(
            ['name' => 'Acme', 'action' => 'GetThing'],
            ['interactive' => false],
        );

        self::assertSame(Command::SUCCESS, $exitCode);

        $root = $this->projectDir.'/src/Infrastructure/Integrations/Acme';
        self::assertFileExists($root.'/AcmeIntegration.php');
        self::assertFileExists($root.'/GetThing/Request/GetThingAction.php');
        self::assertFileExists($root.'/GetThing/Response/GetThingMapper.php');
        self::assertFileExists($root.'/GetThing/Response/GetThingResponse.php');
    }

    #[Test]
    public function noInteractionFirstRunWarnsThatTheIntegrationIsNotRegistered(): void
    {
        $tester = $this->tester();

        $tester->execute(['name' => 'Acme', 'action' => 'GetThing'], ['interactive' => false]);

        $display = self::singleSpaced($tester->getDisplay());
        self::assertFileDoesNotExist($this->projectDir.'/config/packages/integration_engine.yaml');
        self::assertStringContainsString('The "Acme" integration is not registered', $display);
        self::assertStringContainsString('replace the placeholder base_url', $display);
        self::assertStringContainsString("config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/Acme/Acme.yaml'", $display);
    }

    #[Test]
    public function newIntegrationWithAnExistingBundleConfigAsksForItsWiringAndShowsWhatToAdd(): void
    {
        $this->writeBundleConfig('my_api');
        $bundleConfigBefore = (string) file_get_contents($this->projectDir.'/config/packages/integration_engine.yaml');

        $tester = $this->tester();
        $tester->setInputs(['https://other.example.com', 'graphql']);
        $exitCode = $tester->execute(['name' => 'OtherApi', 'action' => 'GetThing']);

        self::assertSame(Command::SUCCESS, $exitCode);

        $display = self::singleSpaced($tester->getDisplay());
        self::assertStringContainsString('Base URL for the "OtherApi" integration', $display);
        self::assertStringNotContainsString('Path for action', $display);
        self::assertStringContainsString('The "OtherApi" integration is not registered', $display);
        self::assertStringNotContainsString('replace the placeholder base_url', $display);
        self::assertStringContainsString('other_api:', $display);
        self::assertStringContainsString("base_url: 'https://other.example.com'", $display);
        self::assertStringContainsString('client: graphql', $display);

        // The user's file is never rewritten: comments and formatting stay theirs.
        self::assertSame($bundleConfigBefore, file_get_contents($this->projectDir.'/config/packages/integration_engine.yaml'));
    }

    #[Test]
    public function registeredIntegrationDoesNotWarn(): void
    {
        $this->writeBundleConfig('my_api');

        $tester = $this->tester();
        $tester->setInputs(['/orders/{id}', 'GET']);
        $tester->execute(['name' => 'MyApi', 'action' => 'GetOrder']);

        self::assertStringNotContainsString('is not registered', $tester->getDisplay());
    }

    #[Test]
    public function firstRunBundleConfigPointsAtTheCustomPath(): void
    {
        $tester = $this->tester();
        $tester->setInputs(['https://api.example.com', 'rest', '/x', 'GET']);
        $tester->execute(['name' => 'MyApi', 'action' => 'GetX', '--path' => 'src/Integrations/', '--namespace' => 'App\Integrations']);

        $bundleConfig = (string) file_get_contents($this->projectDir.'/config/packages/integration_engine.yaml');
        self::assertStringContainsString("config_path: '%kernel.project_dir%/src/Integrations/MyApi/MyApi.yaml'", $bundleConfig);
        self::assertFileExists($this->projectDir.'/src/Integrations/MyApi/MyApi.yaml');
        self::assertStringNotContainsString('is not registered', $tester->getDisplay());
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

    /**
     * SymfonyStyle wraps warnings to the terminal width, so a phrase can be split
     * across lines differently on every machine; compare on single-spaced text.
     */
    private static function singleSpaced(string $display): string
    {
        return (string) preg_replace('/\s+/', ' ', $display);
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
