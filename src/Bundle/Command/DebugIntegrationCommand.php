<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 * @phpstan-type IntegrationConfig array{config_path: ?string, client: string, client_service: ?string}
 * @phpstan-type ActionSummary array{name: string, method: string, path: string, class: string}
 */
#[AsCommand(name: 'debug:integration', description: 'List configured integrations or inspect their actions without sending requests.')]
final class DebugIntegrationCommand extends Command
{
    /** @param array<string, IntegrationConfig> $integrations */
    public function __construct(private readonly array $integrations)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('integration', InputArgument::OPTIONAL, 'The configured integration name')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: txt or json', 'txt')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $format = $input->getOption('format');
        if (!\in_array($format, ['txt', 'json'], true)) {
            $io->error('Output format must be "txt" or "json".');

            return Command::INVALID;
        }

        $name = $input->getArgument('integration');
        if (!\is_string($name)) {
            $rows = [];
            foreach ($this->integrations as $integrationName => $config) {
                $rows[] = [
                    'name' => $integrationName,
                    'client' => $config['client_service'] ?? $config['client'],
                    'config_path' => $config['config_path'] ?? '',
                ];
            }

            if ('json' === $format) {
                $this->writeJson($output, ['integrations' => $rows]);
            } elseif ([] === $rows) {
                $io->note('No integrations configured.');
            } else {
                $io->table(['Integration', 'Client / service', 'Config file'], $this->escapeRows($rows));
            }

            return Command::SUCCESS;
        }

        if (!isset($this->integrations[$name])) {
            $io->error('Unknown integration: '.OutputFormatter::escape($name));

            return Command::FAILURE;
        }

        $config = $this->integrations[$name];

        try {
            $actions = $this->readActions($config['config_path']);
        } catch (\Throwable) {
            // YAML parse errors can include source lines containing credentials.
            $io->error('Unable to inspect actions: check that the integration config is a readable YAML mapping with valid action definitions.');

            return Command::FAILURE;
        }

        if ('json' === $format) {
            $this->writeJson($output, [
                'integration' => $name,
                'client' => $config['client_service'] ?? $config['client'],
                'actions' => $actions,
            ]);
        } else {
            $io->title(OutputFormatter::escape($name));
            if ([] === $actions) {
                $io->note('No actions configured.');
            } else {
                $io->table(['Action', 'Method', 'Path', 'Class'], $this->escapeRows($actions));
            }
        }

        return Command::SUCCESS;
    }

    /** @return list<ActionSummary> */
    private function readActions(?string $path): array
    {
        if (null === $path) {
            throw new \InvalidArgumentException('Missing config path.');
        }

        $parsed = Yaml::parseFile($path);
        if (!\is_array($parsed)) {
            throw new \InvalidArgumentException('Expected a YAML mapping.');
        }

        $actions = [];
        foreach ($parsed as $name => $definition) {
            if ('webhooks' === $name) {
                continue;
            }
            if (!\is_string($name) || !\is_array($definition) || !isset($definition['action']) || !\is_string($definition['action'])) {
                throw new \InvalidArgumentException('Invalid action definition.');
            }

            $method = $definition['method'] ?? 'POST';
            $actionPath = $definition['path'] ?? '/';
            if (!\is_string($method) || !\is_string($actionPath)) {
                throw new \InvalidArgumentException('Invalid action method or path.');
            }

            $actions[] = ['name' => $name, 'method' => $method, 'path' => $actionPath, 'class' => $definition['action']];
        }

        return $actions;
    }

    /**
     * @param list<array<string, string>> $rows
     *
     * @return list<list<string>>
     */
    private function escapeRows(array $rows): array
    {
        return array_map(static fn (array $row): array => array_map(OutputFormatter::escape(...), array_values($row)), $rows);
    }

    /** @param array<string, mixed> $data */
    private function writeJson(OutputInterface $output, array $data): void
    {
        $output->writeln(json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);
    }
}
