<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'make:observability',
    description: 'Generate an observability setup class for an integration',
    aliases: ['make:obs'],
)]
final class MakeObservabilityCommand extends Command
{
    public function __construct(
        private string $projectDir,
        private Filesystem $filesystem = new Filesystem(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('integration', InputArgument::REQUIRED, 'Integration name (e.g., shopify, stripe)')
            ->setHelp('Generates a boilerplate observability setup class with logging, metrics, and error handling stubs.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $integrationArgument = $input->getArgument('integration');
        $integration = strtolower(\is_string($integrationArgument) ? $integrationArgument : '');

        if (!preg_match('/^[a-z][a-z0-9_]*$/', $integration)) {
            $io->error("Integration name must be lowercase alphanumeric (e.g., 'shopify', 'stripe', 'my_api')");

            return Command::INVALID;
        }

        $namespace = $this->guessNamespace();
        $classDir = "{$this->projectDir}/src/Integration/{$this->formatClassName($integration)}";
        $classFile = "{$classDir}/{$this->formatClassName($integration)}ObservabilitySetup.php";
        $servicesFile = "{$this->projectDir}/config/services.yaml";

        if ($this->filesystem->exists($classFile)) {
            $io->warning("Class already exists: {$classFile}");

            return Command::FAILURE;
        }

        $className = $this->formatClassName($integration);
        $classContent = $this->generateClass($namespace, $className, $integration);

        $this->filesystem->dumpFile($classFile, $classContent);
        $io->success("Generated: {$classFile}");

        $this->updateServicesYaml($servicesFile, $integration, $className, $io);

        $io->section('Next Steps');
        $io->listing([
            "Customize {$className}ObservabilitySetup in {$classFile}",
            'Configure async logging in config/packages/monolog.yaml',
            'See OBSERVABILITY.md for performance options',
            "Test: php bin/console debug:autowiring {$className}ObservabilitySetup",
        ]);

        return Command::SUCCESS;
    }

    private function generateClass(string $namespace, string $className, string $integration): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\\Integration\\{$className};

use IntegrationEngine\\Core\\Lifecycle\\ActionFailed;
use IntegrationEngine\\Core\\Lifecycle\\LifecycleEventDispatcher;
use Psr\\Log\\LoggerInterface;

/**
 * Observability setup for {$className} integration.
 * Provides logging, metrics, and error tracking.
 *
 * Usage:
 *   \$dispatcher = new LifecycleEventDispatcher();
 *   \$setup = new {$className}ObservabilitySetup(\$logger, ...);
 *   \$setup->register(\$dispatcher);
 */
class {$className}ObservabilitySetup
{
    public function __construct(
        private LoggerInterface \$logger,

        // private PrometheusRegistry \$prometheus,
        // private SlackNotifier \$slack,
    ) {}

    /**
     * Register observability observers for {$integration} integration.
     */
    public function register(LifecycleEventDispatcher \$dispatcher): void
    {

        \\IntegrationEngine\\Infrastructure\\Lifecycle\\ObservabilitySetup::register(
            \$dispatcher,
            \$this->logger,
            [
                'logging' => true,
                'log_level' => 'info',
                'slow_request_threshold_ms' => 3000,
                'integration_filter' => '{$integration}',
            ]
        );


        \\IntegrationEngine\\Infrastructure\\Lifecycle\\ObservabilitySetup::register(
            \$dispatcher,
            \$this->logger,
            [
                'metrics_callback' => fn(\$event) => \$this->recordMetrics(\$event),
                'integration_filter' => '{$integration}',
            ]
        );


        \\IntegrationEngine\\Infrastructure\\Lifecycle\\ObservabilitySetup::register(
            \$dispatcher,
            \$this->logger,
            [
                'error_callback' => fn(\$event) => \$this->recordError(\$event),
                'integration_filter' => '{$integration}',
            ]
        );
    }

    /**
     * Record custom metrics (Prometheus, Datadog, etc.).
     * Called on ActionCompleted and ActionFailed.
     */
    private function recordMetrics(\$event): void
    {


        // \$this->prometheus->histogram(
        //     '{$integration}_api_duration_ms',
        //     \$event->durationMs(),
        //     ['action' => \$event->action()->getName()]
        // );
    }

    /**
     * Handle errors (send to Sentry, alert on critical failures).
     */
    private function recordError(ActionFailed \$event): void
    {


        // \\Sentry\\captureException(\$event->error(), [
        //     'tags' => [
        //         'integration' => '{$integration}',
        //         'action' => \$event->action()->getName(),
        //     ],
        //     'extra' => ['duration_ms' => \$event->durationMs()],
        // ]);
    }
}
PHP;
    }

    private function updateServicesYaml(string $path, string $integration, string $className, SymfonyStyle $io): void
    {
        if (!$this->filesystem->exists($path)) {
            $io->warning("services.yaml not found at {$path}. Add this manually:");
            $this->printServicesYamlEntry($className, $integration, $io);

            return;
        }

        $content = file_get_contents($path);
        if (false === $content) {
            $io->error("Could not read {$path}");

            return;
        }

        $entry = <<<YAML

  app.{$integration}.observability:
    class: App\\Integration\\{$className}\\{$className}ObservabilitySetup
    calls:
      - [register, ['@IntegrationEngine\\Core\\Lifecycle\\LifecycleEventDispatcher', '@logger']]
YAML;

        if (false === strpos($content, "{$integration}.observability")) {
            file_put_contents($path, $content."\n".$entry."\n");
            $io->success("Updated: {$path}");
        } else {
            $io->note('Observability entry already in services.yaml');
        }
    }

    private function printServicesYamlEntry(string $className, string $integration, SymfonyStyle $io): void
    {
        $io->writeln(<<<YAML

Add to config/services.yaml:

  app.{$integration}.observability:
    class: App\\Integration\\{$className}\\{$className}ObservabilitySetup
    calls:
      - [register, ['@IntegrationEngine\\Core\\Lifecycle\\LifecycleEventDispatcher', '@logger']]

YAML);
    }

    private function guessNamespace(): string
    {
        $composerFile = "{$this->projectDir}/composer.json";
        if (!$this->filesystem->exists($composerFile)) {
            return 'App';
        }

        $content = file_get_contents($composerFile);
        if (false === $content) {
            return 'App';
        }

        $composer = json_decode($content, true);
        if (!\is_array($composer)) {
            return 'App';
        }

        $autoload = $composer['autoload'] ?? null;
        $psr4 = \is_array($autoload) ? ($autoload['psr-4'] ?? null) : null;
        if (!\is_array($psr4)) {
            return 'App';
        }

        foreach ($psr4 as $namespace => $path) {
            if ('src/' === $path) {
                return rtrim($namespace, '\\');
            }
        }

        return 'App';
    }

    private function formatClassName(string $name): string
    {
        return str_replace('_', '', ucwords($name, '_'));
    }
}
