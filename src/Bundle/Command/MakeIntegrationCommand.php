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
    description: 'Generates the skeleton for a new integration or adds an action to an existing one',
)]
final class MakeIntegrationCommand extends Command
{
    private const METHODS = ['GET', 'POST', 'PUT', 'DELETE'];

    public function __construct(
        private readonly string $projectDir,
        private readonly IntegrationFileGenerator $generator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Integration name (e.g. Iberia)')
            ->addArgument('action', InputArgument::REQUIRED, 'Action name (e.g. GetOrders)')
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'Base namespace', 'App\\Infrastructure\\Integrations')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Base path', 'src/Infrastructure/Integrations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name      = (string) $input->getArgument('name');
        $action    = (string) $input->getArgument('action');
        $namespace = rtrim((string) $input->getOption('namespace'), '\\');
        $basePath  = rtrim((string) $input->getOption('path'), '/');

        // ── Ask for HTTP method ───────────────────────────────────────────────
        $method = $io->choice(
            question: 'HTTP method for this action',
            choices: self::METHODS,
            default: 'GET',
        );

        $ctx = new IntegrationContext(
            name: $name,
            action: $action,
            method: $method,
            baseNamespace: $namespace,
            basePath: $this->projectDir . '/' . $basePath,
        );

        // ── Integration root files (only if new integration) ─────────────────
        $integrationExists = $this->generator->integrationExists($ctx);

        if ($integrationExists) {
            $io->note("Integration \"{$name}\" already exists — skipping root files.");
        } else {
            $io->title("Generating integration: {$name}");
            foreach ($this->generator->generateIntegrationFiles($ctx) as $file => $content) {
                $this->writeFile($file, $content, $io);
            }
        }

        // ── Action files ─────────────────────────────────────────────────────
        $io->section("Generating action: {$action} [{$method}]");

        $this->describeAction($ctx, $io);

        foreach ($this->generator->generateActionFiles($ctx) as $file => $content) {
            $this->writeFile($file, $content, $io);
        }

        // ── Append entry to config yaml ───────────────────────────────────────
        $configPath = $this->generator->appendActionToConfig($ctx);
        $io->text("  updated  {$configPath}");

        $io->success('Done.');

        return Command::SUCCESS;
    }

    private function describeAction(IntegrationContext $ctx, SymfonyStyle $io): void
    {
        $lines = ['<info>Request/</info>' . $ctx->action . 'Action.php'];

        if ($ctx->hasBody()) {
            $lines[] = '<info>Request/</info>' . $ctx->action . 'Body.php';
        }

        if ($ctx->hasResponse()) {
            $lines[] = '<info>Response/</info>' . $ctx->action . 'Mapper.php';
            $lines[] = '<info>Response/</info>' . $ctx->action . 'Response.php';
        } else {
            $lines[] = '<comment>Response layer skipped (DELETE has no response)</comment>';
        }

        $io->listing($lines);
    }

    private function writeFile(string $filePath, string $content, SymfonyStyle $io): void
    {
        $dir = dirname($filePath);

        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            $io->error("Cannot create dir: {$dir}");
            return;
        }

        if (file_exists($filePath)) {
            $io->warning("Skipped (already exists): {$filePath}");
            return;
        }

        file_put_contents($filePath, $content);
        $io->text("  created  {$filePath}");
    }
}