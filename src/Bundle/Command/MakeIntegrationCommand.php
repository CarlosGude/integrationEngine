<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Command;

use IntegrationEngine\Bundle\Generator\IntegrationContext;
use IntegrationEngine\Bundle\Generator\IntegrationFileGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'make:integration',
    description: 'Generates a new integration skeleton'
)]
final class MakeIntegrationCommand extends Command
{
    public function __construct(
        private readonly string $projectDir,
        private readonly IntegrationFileGenerator $generator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED)
            ->addArgument('action', InputArgument::REQUIRED)
            ->addOption(
                'namespace',
                null,
                InputOption::VALUE_REQUIRED,
                'Base namespace',
                'App\Infrastructure\Integrations'
            )
            ->addOption(
                'path',
                null,
                InputOption::VALUE_REQUIRED,
                'Base path',
                'src/Infrastructure/Integrations'
            )
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

        if (!\is_string($nameArg) || !\is_string($actionArg) || !\is_string($namespaceOpt) || !\is_string($pathOpt)) {
            $io->error('Invalid arguments provided.');

            return Command::FAILURE;
        }

        $name = $nameArg;
        $action = $actionArg;
        $force = (bool) $input->getOption('force');
        $baseNamespace = rtrim($namespaceOpt, '\\');
        $basePath = rtrim($pathOpt, '/');

        $integrationPath = $this->projectDir.'/'.$basePath.'/'.$name;

        $ctx = new IntegrationContext(
            name: $name,
            action: $action,
            method: 'GET',
            path: '/',
            baseNamespace: $baseNamespace,
            basePath: $integrationPath,
        );

        $io->title(\sprintf('Generating integration: %s', $name));

        // 1. Integration skeleton
        foreach ($this->generator->generateIntegrationFiles($ctx) as $file => $content) {
            $this->writeFile($file, $content, $io, $force);
        }

        // 2. First action skeleton
        foreach ($this->generator->generateActionFiles($ctx) as $file => $content) {
            $this->writeFile($file, $content, $io, $force);
        }

        // 3. YAML config
        $this->generator->appendActionToConfig($ctx);

        $io->success('Done.');

        return Command::SUCCESS;
    }

    private function writeFile(
        string $filePath,
        string $content,
        SymfonyStyle $io,
        bool $force
    ): void {
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
}
