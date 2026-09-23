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

        $this->updateServicesYaml($servicesFile, $namespace, $integration, $className, $io);

        $io->section('Next Steps');
        $io->listing([
            "Customize {$className}ObservabilitySetup in {$classFile}",
            "Inject the app.{$integration}.observability service into an application service that is instantiated before integration requests; defining a private service alone does not activate observers.",
            'Use the same LifecycleEventDispatcher instance for this setup and the integration engine.',
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

use IntegrationEngine\\Core\\Event\\ResponseMapped;
use IntegrationEngine\\Core\\Event\\RequestFailed;
use IntegrationEngine\\Core\\Lifecycle\\LifecycleEventDispatcher;
use Psr\\Log\\LoggerInterface;

/**
 * Observability setup for {$className} integration.
 * Provides logging, metrics, and error tracking.
 *
 * Usage:
 *   // Use the same dispatcher instance passed to the integration engine.
 *   \$setup = new {$className}ObservabilitySetup(\$logger);
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
                'metrics_callback' => fn(ResponseMapped|RequestFailed \$event) => \$this->recordMetrics(\$event),
                'error_callback' => fn(RequestFailed \$event) => \$this->recordError(\$event),
                'integration_filter' => '{$integration}',
            ]
        );
    }

    /**
     * Record custom metrics (Prometheus, Datadog, etc.).
     * Called on ResponseMapped and RequestFailed.
     */
    private function recordMetrics(ResponseMapped|RequestFailed \$event): void
    {
        // \$this->prometheus->histogram(
        //     '{$integration}_api_duration_ms',
        //     \$event->durationMs,
        //     ['action' => \$event->action]
        // );
    }

    /**
     * Handle errors (send to Sentry, alert on critical failures).
     */
    private function recordError(RequestFailed \$event): void
    {
        // RequestFailed intentionally carries no Throwable object or raw
        // upstream message. Send bounded scalar metadata to your error backend:
        // \$this->errors->capture([
        //     'integration' => '{$integration}',
        //     'action' => \$event->action,
        //     'exception_class' => \$event->exceptionClass,
        //     'status_code' => \$event->statusCode,
        //     'duration_ms' => \$event->durationMs,
        // ]);
    }
}
PHP;
    }

    private function updateServicesYaml(string $path, string $namespace, string $integration, string $className, SymfonyStyle $io): void
    {
        if (!$this->filesystem->exists($path)) {
            $io->warning("services.yaml not found at {$path}. Add this manually:");
            $this->printServicesYamlEntry($namespace, $className, $integration, $io);

            return;
        }

        $content = file_get_contents($path);
        if (false === $content) {
            $io->error("Could not read {$path}");

            return;
        }

        $entry = <<<YAML

  app.{$integration}.observability:
    class: {$namespace}\\Integration\\{$className}\\{$className}ObservabilitySetup
    arguments: ['@logger']
    calls:
      - [register, ['@IntegrationEngine\\Core\\Lifecycle\\LifecycleEventDispatcher']]
YAML;

        if (false === strpos($content, "{$integration}.observability")) {
            $content = rtrim($content)."\n";
            if (!preg_match('/^services:[ \t]*(?:#[^\r\n]*)?\r?\n((?:[ \t]+[^\r\n]*\r?\n|[ \t]*\r?\n|#[^\r\n]*\r?\n)*)/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
                $io->warning('Could not locate a services mapping. Add this manually:');
                $this->printServicesYamlEntry($namespace, $className, $integration, $io);

                return;
            }

            $indent = preg_match('/^( +)\S/m', $matches[1][0], $indentation) ? $indentation[1] : '    ';
            $entry = str_replace("\n  ", "\n".$indent, $entry);
            $offset = $matches[0][1] + \strlen($matches[0][0]);
            $this->filesystem->dumpFile($path, substr($content, 0, $offset).$entry."\n".substr($content, $offset));
            $io->success("Updated: {$path}");
        } else {
            $io->note('Observability entry already in services.yaml');
        }
    }

    private function printServicesYamlEntry(string $namespace, string $className, string $integration, SymfonyStyle $io): void
    {
        $io->writeln(<<<YAML

Add to config/services.yaml:

  app.{$integration}.observability:
    class: {$namespace}\\Integration\\{$className}\\{$className}ObservabilitySetup
    arguments: ['@logger']
    calls:
      - [register, ['@IntegrationEngine\\Core\\Lifecycle\\LifecycleEventDispatcher']]

YAML);
    }

    private function guessNamespace(): string
    {
        // Each step degrades to null so any missing/invalid piece falls through to 'App'.
        $composerFile = "{$this->projectDir}/composer.json";
        $content = $this->filesystem->exists($composerFile) ? file_get_contents($composerFile) : false;
        $composer = false === $content ? null : json_decode($content, true);
        $autoload = \is_array($composer) ? ($composer['autoload'] ?? null) : null;
        $psr4 = \is_array($autoload) ? ($autoload['psr-4'] ?? null) : null;

        foreach (\is_array($psr4) ? $psr4 : [] as $namespace => $path) {
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
