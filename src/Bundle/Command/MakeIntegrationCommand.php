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
    description: 'Generates the skeleton for a new integration',
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
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'Base namespace', 'App\\Infrastructure\\Integrations')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Base path', 'src/Infrastructure/Integrations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = (string) $input->getArgument('name');
        $action = (string) $input->getArgument('action');

        $namespace = rtrim((string) $input->getOption('namespace'), '\\');
        $basePath = rtrim((string) $input->getOption('path'), '/');

        $context = new IntegrationContext(
            name: $name,
            action: $action,
            baseNamespace: $namespace,
            basePath: $this->projectDir . '/' . $basePath,
        );

        $io->title("Generating integration: {$name}");

        $files = $this->generator->generate($context);

        foreach ($files as $file => $content) {
            $this->writeFile($file, $content, $io);
        }

        $io->success('Done.');

        return Command::SUCCESS;
    }

    private function writeFile(string $filePath, string $content, SymfonyStyle $io): void
    {
        $dir = dirname($filePath);

        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            $io->error("Cannot create dir: {$dir}");
            return;
        }

        if (file_exists($filePath)) {
            $io->warning("Skipped: {$filePath}");
            return;
        }

        file_put_contents($filePath, $content);
        $io->text("created {$filePath}");
    }
}