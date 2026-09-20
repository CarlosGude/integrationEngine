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
    description: 'Generates a new webhook parser, event DTO, and request handler'
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

        $verifierTypeChoice = $io->choice(
            'Signature verification type',
            ['hmac_sha256', 'timestamped_hmac'],
            'hmac_sha256'
        );
        $verifierType = \is_string($verifierTypeChoice) ? $verifierTypeChoice : 'hmac_sha256';

        $headerNameInput = $io->ask(
            'Signature header name (e.g., X-Webhook-Signature)',
            'X-Webhook-Signature',
            static function (mixed $value): string {
                $trimmed = \is_string($value) ? trim($value) : '';
                if ('' === $trimmed) {
                    throw new \InvalidArgumentException('Header name cannot be empty.');
                }

                return $trimmed;
            }
        );
        $headerName = \is_string($headerNameInput) ? $headerNameInput : 'X-Webhook-Signature';

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

        $io->success('Done.');

        $io->writeln([
            'Route it, so the webhook reaches the parser:',
            '',
            '    # config/packages/framework.yaml',
            '    framework:',
            '        webhook:',
            '            routing:',
            \sprintf('                %s:                # POST /webhook/%s', $ctx->routingKey(), $ctx->routingKey()),
            \sprintf('                    service: %s', $ctx->parserClassFqn()),
            "                    secret: '%env(WEBHOOK_SECRET)%'",
            '',
        ]);

        return Command::SUCCESS;
    }
}
