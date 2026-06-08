<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Command;

use IntegrationEngine\Bundle\Generator\IntegrationContext;
use IntegrationEngine\Bundle\Generator\IntegrationFileGenerator;
use IntegrationEngine\Core\Contract\ClientAdapterInterface;
use IntegrationEngine\Infrastructure\Http\ClientAdapterResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'make:integration',
    description: 'Generates a new integration skeleton or adds an action to an existing one'
)]
final class MakeIntegrationCommand extends Command
{
    public function __construct(
        private readonly string $projectDir,
        private readonly IntegrationFileGenerator $generator,
        private readonly ClientAdapterResolver $adapterResolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED)
            ->addArgument('action', InputArgument::OPTIONAL)
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'Base namespace', 'App\Infrastructure\Integrations')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Base path', 'src/Infrastructure/Integrations')
            ->addOption('force', null, InputOption::VALUE_NONE)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $nameArg = $input->getArgument('name');
        $actionArg = $input->getArgument('action');
        $namespaceOpt = $input->getOption('namespace');
        $pathOpt = $input->getOption('path');

        if (!\is_string($nameArg) || !\is_string($namespaceOpt) || !\is_string($pathOpt)) {
            $io->error('Invalid arguments provided.');

            return Command::FAILURE;
        }

        $name = $nameArg;
        $force = (bool) $input->getOption('force');
        $baseNamespace = rtrim($namespaceOpt, '\\');
        $basePath = rtrim($pathOpt, '/');
        $integrationPath = $this->projectDir.'/'.$basePath.'/'.$name;
        $bundleConfigPath = $this->projectDir.'/config/packages/integration_engine.yaml';
        $bundleConfigExists = file_exists($bundleConfigPath);

        [$clientType, $baseUrl] = $this->resolveClientConfig($io, $bundleConfigPath, $name, $bundleConfigExists);

        try {
            $adapterClass = $this->adapterResolver->resolve($clientType);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        /** @var class-string<ClientAdapterInterface> $adapterClass */
        $requiresPath = $adapterClass::requiresPath();
        $requiresMethod = $adapterClass::requiresMethod();

        $action = $this->resolveActionName($io, $actionArg);
        if (null === $action) {
            $io->error('Action name could not be resolved. Use --no-interaction with the action argument.');

            return Command::FAILURE;
        }

        [$actionPath, $method] = $this->resolvePathAndMethod($io, $action, $requiresPath, $requiresMethod);

        $ctx = new IntegrationContext(
            name: $name,
            action: $action,
            method: $method,
            path: $actionPath,
            baseNamespace: $baseNamespace,
            basePath: $integrationPath,
            clientType: $clientType,
            adapterRequiresPath: $requiresPath,
            adapterRequiresMethod: $requiresMethod,
        );

        $io->title(\sprintf('Generating integration: %s / %s', $name, $action));

        if (!$bundleConfigExists && null !== $baseUrl) {
            $this->createBundleConfig($bundleConfigPath, $name, $baseUrl, $clientType, $io);
        }

        if (!$this->generator->integrationExists($ctx)) {
            foreach ($this->generator->generateIntegrationFiles($ctx) as $file => $content) {
                $this->writeFile($file, $content, $io, $force);
            }
        }

        foreach ($this->generator->generateActionFiles($ctx) as $file => $content) {
            $this->writeFile($file, $content, $io, $force);
        }

        $configPath = $this->generator->appendActionToConfig($ctx);
        $io->text("  updated  {$configPath}");
        $io->success('Done.');

        return Command::SUCCESS;
    }

    /**
     * Resolves the client type and base URL.
     * On first run (no bundle config), asks the user interactively.
     * On subsequent runs, reads the client type from the existing YAML.
     *
     * @return array{string, null|string} [clientType, baseUrl|null]
     */
    private function resolveClientConfig(
        SymfonyStyle $io,
        string $bundleConfigPath,
        string $name,
        bool $bundleConfigExists,
    ): array {
        $clientType = $this->detectClientType($bundleConfigPath, $name);
        $baseUrl = null;

        if (!$bundleConfigExists) {
            $baseUrl = $io->ask(
                \sprintf('Base URL for the "%s" integration (e.g. https://api.example.com)', $name),
                null,
                static function (?string $value): string {
                    if (null === $value || '' === trim($value)) {
                        throw new \InvalidArgumentException('Base URL cannot be empty.');
                    }

                    return trim($value);
                }
            );

            $availableTypes = array_keys($this->adapterResolver->all());
            $clientType = $io->choice('Client type', $availableTypes, 'rest');
        }

        return [$clientType, $baseUrl];
    }

    /**
     * Resolves the action name from the argument or asks interactively.
     */
    private function resolveActionName(SymfonyStyle $io, mixed $actionArg): ?string
    {
        if (\is_string($actionArg) && '' !== $actionArg) {
            return $actionArg;
        }

        $action = $io->ask(
            'Name of the first action (e.g. GetEmployees)',
            null,
            static function (?string $value): string {
                if (null === $value || '' === trim($value)) {
                    throw new \InvalidArgumentException('Action name cannot be empty.');
                }

                return trim($value);
            }
        );

        return \is_string($action) && '' !== $action ? $action : null;
    }

    /**
     * Asks for path and method only when the adapter requires them.
     *
     * @return array{string, string} [actionPath, method]
     */
    private function resolvePathAndMethod(
        SymfonyStyle $io,
        string $action,
        bool $requiresPath,
        bool $requiresMethod,
    ): array {
        $actionPath = '/';
        $method = 'POST';

        if ($requiresPath) {
            $actionPath = $io->ask(
                \sprintf('Path for action "%s" (e.g. /employees or /employees/{id})', $action),
                '/',
                static function (?string $value): string {
                    $value = trim((string) $value);

                    return '' === $value ? '/' : $value;
                }
            );
        }

        if ($requiresMethod) {
            $method = $io->choice('HTTP method', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], 'GET');
        }

        return [$actionPath, $method];
    }

    private function detectClientType(string $bundleConfigPath, string $name): string
    {
        if (!file_exists($bundleConfigPath)) {
            return 'rest';
        }

        try {
            /** @var array<string, mixed> $yaml */
            $yaml = Yaml::parseFile($bundleConfigPath);
            $snakeName = $this->toSnakeCase($name);

            return (string) ($yaml['integration_engine']['integrations'][$snakeName]['client'] ?? 'rest');
        } catch (\Throwable) {
            return 'rest';
        }
    }

    private function createBundleConfig(
        string $configPath,
        string $name,
        string $baseUrl,
        string $clientType,
        SymfonyStyle $io,
    ): void {
        $dir = \dirname($configPath);

        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            $io->warning("Could not create config directory: {$dir}");

            return;
        }

        $snakeName = $this->toSnakeCase($name);
        $clientLine = 'rest' !== $clientType ? "\n            client: {$clientType}" : '';

        $content = <<<YAML
integration_engine:
    integrations:
        {$snakeName}:
            base_url: '{$baseUrl}'{$clientLine}
            config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/{$name}/{$name}.yaml'
YAML;

        file_put_contents($configPath, $content.PHP_EOL);
        $io->text("  created  {$configPath}");
    }

    private function writeFile(string $filePath, string $content, SymfonyStyle $io, bool $force): void
    {
        $dir = \dirname($filePath);

        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            $io->error("Cannot create directory: {$dir}");

            return;
        }

        $exists = file_exists($filePath);

        if ($exists && !$force) {
            $io->warning("Skipped (already exists): {$filePath}");

            return;
        }

        file_put_contents($filePath, $content);
        $io->text($exists ? "  updated  {$filePath}" : "  created  {$filePath}");
    }

    private function toSnakeCase(string $value): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/u', '_$0', $value) ?? $value);
    }
}
