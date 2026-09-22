<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Command;

use IntegrationEngine\Bundle\Generator\GeneratedFileWriter;
use IntegrationEngine\Bundle\Generator\WebhookContext;
use IntegrationEngine\Bundle\Generator\WebhookFileGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'make:webhook',
    description: 'Generates a webhook event, mapper, and integration YAML'
)]
final class MakeWebhookCommand extends Command
{
    public function __construct(
        private readonly string $projectDir,
        private readonly WebhookFileGenerator $generator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('integration', InputArgument::REQUIRED, 'Integration name (e.g., stripe, paypal)')
            ->addArgument('event', InputArgument::REQUIRED, 'Webhook event type (e.g., charge.succeeded)')
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'Base namespace', 'App\Webhooks')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Base path', 'src/Webhooks')
            ->addOption('force', null, InputOption::VALUE_NONE)
            ->addOption('signature-type', null, InputOption::VALUE_REQUIRED, 'Signature scheme', 'hmac_sha256')
            ->addOption('signature-header', null, InputOption::VALUE_REQUIRED, 'Signature header', 'X-Webhook-Signature')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $integrationArg = $input->getArgument('integration');
        $eventArg = $input->getArgument('event');
        $namespaceOpt = $input->getOption('namespace');
        $pathOpt = $input->getOption('path');

        if (!\is_string($integrationArg) || !\is_string($eventArg) || !\is_string($namespaceOpt) || !\is_string($pathOpt)) {
            $io->error('Invalid arguments provided.');

            return Command::FAILURE;
        }

        $integration = $integrationArg;
        $event = $eventArg;
        $force = (bool) $input->getOption('force');
        $baseNamespace = rtrim($namespaceOpt, '\\');
        $basePath = rtrim($pathOpt, '/');

        $verifierType = $input->getOption('signature-type');
        $headerName = $input->getOption('signature-header');
        if (!\is_string($verifierType) || !\in_array($verifierType, ['hmac_sha256', 'hmac_base64', 'timestamped_hmac'], true) || !\is_string($headerName) || '' === trim($headerName)) {
            $io->error('Invalid signature type or header.');

            return Command::INVALID;
        }

        return $this->generate($io, $integration, $event, $verifierType, $headerName, $baseNamespace, $basePath, $force);
    }

    private function generate(
        SymfonyStyle $io,
        string $integration,
        string $event,
        string $verifierType,
        string $headerName,
        string $baseNamespace,
        string $basePath,
        bool $force,
    ): int {
        $ctx = new WebhookContext(
            integration: $integration,
            event: $event,
            verifierType: $verifierType,
            headerName: $headerName,
            baseNamespace: $baseNamespace,
            basePath: $this->projectDir.'/'.$basePath,
        );

        $io->title(\sprintf('Generating webhook: %s / %s', $integration, $event));

        foreach ($this->generator->generateFiles($ctx) as $file => $content) {
            GeneratedFileWriter::write($file, $content, $io, $force);
        }

        $yamlPath = $ctx->generationPath().'/'.ucfirst($ctx->integration).'.yaml';
        $existing = is_file($yamlPath) ? file_get_contents($yamlPath) : '';
        if (false === $existing) {
            throw new \RuntimeException('Cannot read integration YAML.');
        }
        GeneratedFileWriter::write($yamlPath, $this->generator->mergeYaml($ctx, $existing, $force), $io, true);
        $io->success('Done. Configure config_path with the generated YAML and route to integration_engine.webhook_parser.'.strtolower($ctx->integration).'.');

        return Command::SUCCESS;
    }
}
