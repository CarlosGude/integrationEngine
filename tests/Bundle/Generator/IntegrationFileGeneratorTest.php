<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Bundle\Generator;

use IntegrationEngine\Bundle\Generator\IntegrationContext;
use IntegrationEngine\Bundle\Generator\IntegrationFileGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IntegrationFileGeneratorTest extends TestCase
{
    private string $tmpDir;
    private IntegrationFileGenerator $generator;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/ie_generator_'.uniqid();
        $this->generator = new IntegrationFileGenerator();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    #[Test]
    public function integrationFilesContainOnlyTheIntegrationClass(): void
    {
        $files = $this->generator->generateIntegrationFiles($this->context());

        self::assertSame(
            [$this->tmpDir.'/MyApi/MyApiIntegration.php'],
            array_keys($files)
        );
        self::assertStringContainsString('final class MyApiIntegration', $files[$this->tmpDir.'/MyApi/MyApiIntegration.php']);
    }

    #[Test]
    public function actionFilesIncludeMapperAndResponseWhenActionHasResponse(): void
    {
        $files = $this->generator->generateActionFiles($this->context());

        self::assertSame([
            $this->tmpDir.'/MyApi/GetEmployees/Request/GetEmployeesAction.php',
            $this->tmpDir.'/MyApi/GetEmployees/Response/GetEmployeesMapper.php',
            $this->tmpDir.'/MyApi/GetEmployees/Response/GetEmployeesResponse.php',
        ], array_keys($files));
    }

    #[Test]
    public function actionFilesForDeleteOmitResponseLayer(): void
    {
        $ctx = $this->context()->with(action: 'DeleteEmployee', method: 'DELETE');

        $files = $this->generator->generateActionFiles($ctx);

        self::assertSame(
            [$this->tmpDir.'/MyApi/DeleteEmployee/Request/DeleteEmployeeAction.php'],
            array_keys($files)
        );
    }

    #[Test]
    public function appendActionToConfigCreatesTheFileOnFirstAction(): void
    {
        mkdir($this->tmpDir.'/MyApi', 0o755, true);

        $configPath = $this->generator->appendActionToConfig($this->context());

        self::assertSame($this->tmpDir.'/MyApi/MyApi.yaml', $configPath);
        $content = (string) file_get_contents($configPath);
        self::assertStringContainsString('GetEmployees:', $content);
        self::assertStringContainsString('method: GET', $content);
    }

    #[Test]
    public function appendActionToConfigAppendsWithoutErasingPreviousActions(): void
    {
        mkdir($this->tmpDir.'/MyApi', 0o755, true);

        $this->generator->appendActionToConfig($this->context());
        $configPath = $this->generator->appendActionToConfig(
            $this->context()->with(action: 'GetEmployee', path: '/employees/{id}')
        );

        $content = (string) file_get_contents($configPath);
        self::assertStringContainsString('GetEmployees:', $content);
        self::assertStringContainsString('GetEmployee:', $content);
        self::assertStringContainsString('path: /employees/{id}', $content);
    }

    #[Test]
    public function integrationExistsReflectsTheDirectoryOnDisk(): void
    {
        $ctx = $this->context();

        self::assertFalse($this->generator->integrationExists($ctx));

        mkdir($this->tmpDir.'/MyApi', 0o755, true);

        self::assertTrue($this->generator->integrationExists($ctx));
    }

    private function context(): IntegrationContext
    {
        return new IntegrationContext(
            name: 'MyApi',
            action: 'GetEmployees',
            method: 'GET',
            path: '/employees',
            baseNamespace: 'App\Infrastructure\Integrations',
            basePath: $this->tmpDir.'/MyApi',
        );
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
