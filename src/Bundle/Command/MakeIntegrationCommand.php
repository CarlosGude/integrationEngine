<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'make:integration',
    description: 'Generates the skeleton for a new integration (Integration, Action, Body, Mapper, Response, Client, YAML config).',
)]
final class MakeIntegrationCommand extends Command
{
    public function __construct(
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Integration name in PascalCase (e.g. Stripe, AcmeApi)')
            ->addArgument('action', InputArgument::REQUIRED, 'First action name in PascalCase (e.g. ChargeCard, GetOrders)')
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'Base namespace for the integration', 'App\\Integration')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Base path to generate files', 'src/Integration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name      = $input->getArgument('name');
        $action    = $input->getArgument('action');
        $namespace = rtrim($input->getOption('namespace'), '\\');
        $basePath  = rtrim($input->getOption('path'), '/');

        $integrationNamespace = sprintf('%s\\%s', $namespace, $name);
        $integrationPath      = sprintf('%s/%s/%s', $this->projectDir, $basePath, $name);

        $io->title(sprintf('Generating integration: %s', $name));

        $files = $this->buildFiles($name, $action, $integrationNamespace, $integrationPath);

        foreach ($files as $filePath => $content) {
            $this->writeFile($filePath, $content, $io);
        }

        $this->printNextSteps($io, $name, $action, $integrationNamespace, $integrationPath);

        return Command::SUCCESS;
    }

    private function buildFiles(string $name, string $action, string $ns, string $path): array
    {
        return [
            sprintf('%s/%sIntegration.php', $path, $name)          => $this->renderIntegration($name, $ns),
            sprintf('%s/Action/%sAction.php', $path, $action)       => $this->renderAction($action, $ns),
            sprintf('%s/Body/%sBody.php', $path, $action)           => $this->renderBody($action, $ns),
            sprintf('%s/Mapper/%sMapper.php', $path, $action)       => $this->renderMapper($action, $ns),
            sprintf('%s/Response/%sResponse.php', $path, $action)   => $this->renderResponse($action, $ns),
            sprintf('%s/%sHttpClient.php', $path, $name)            => $this->renderClient($name, $ns),
            sprintf('%s/config/%s.yaml', $path, strtolower($name))  => $this->renderYaml($name, $action, $ns),
        ];
    }

    private function renderIntegration(string $name, string $ns): string
    {
        $constValue = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

use IntegrationEngine\Core\Registry\IntegrationName;

final class {$name}Integration implements IntegrationName
{
    public const string NAME = '{$constValue}';
}
PHP;
    }

    private function renderAction(string $action, string $ns): string
    {
        $actionName = lcfirst($action);

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns}\Action;

use IntegrationEngine\Core\Contract\AbstractAction;
use {$ns}\Mapper\{$action}Mapper;

final readonly class {$action}Action extends AbstractAction
{
    public static function getName(): string
    {
        return '{$actionName}';
    }

    public static function getMapper(): string
    {
        return {$action}Mapper::class;
    }
}
PHP;
    }

    private function renderBody(string $action, string $ns): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns}\Body;

use IntegrationEngine\Core\Contract\ActionBodyInterface;

final readonly class {$action}Body implements ActionBodyInterface
{
    public function __construct(
        // TODO: add your fields here
    ) {
    }

    public function toArray(): array
    {
        return [
            // TODO: map your fields here
        ];
    }
}
PHP;
    }

    private function renderMapper(string $action, string $ns): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns}\Mapper;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\AbstractMapper;
use IntegrationEngine\Core\Contract\ResponseInterface;
use {$ns}\Action\{$action}Action;
use {$ns}\Response\{$action}Response;

final class {$action}Mapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return {$action}Action::class;
    }

    protected static function transform(AbstractAction \$action, array \$response): ResponseInterface
    {
        // \$action is guaranteed to be {$action}Action
        // TODO: document the expected \$response structure from the API here
        return new {$action}Response(
            // TODO: map \$response fields to the DTO
        );
    }
}
PHP;
    }

    private function renderResponse(string $action, string $ns): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns}\Response;

use IntegrationEngine\Core\Contract\ResponseInterface;

final readonly class {$action}Response implements ResponseInterface
{
    public function __construct(
        // TODO: add your fields here
    ) {
    }

    public function toArray(): array
    {
        return [
            // TODO: map your fields here
        ];
    }
}
PHP;
    }

    private function renderClient(string $name, string $ns): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;

/**
 * HTTP client for {$name}.
 *
 * Extend SymfonyHttpClientAdapter to customise behaviour (request signing,
 * retries, custom headers, etc.), or replace this class with a full
 * ClientInterface implementation for complete control.
 *
 * Register as a Symfony service and reference it via client_service
 * in config/packages/integration_engine.yaml.
 */
final class {$name}HttpClient extends SymfonyHttpClientAdapter
{
}
PHP;
    }

    private function renderYaml(string $name, string $action, string $ns): string
    {
        $actionClass = sprintf('%s\\Action\\%sAction', $ns, $action);
        $actionKey   = lcfirst($action);
        $nameConst   = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));

        return <<<YAML
# {$name} integration — action definitions
#
# Register this file in config/packages/integration_engine.yaml:
#
# integration_engine:
#     integrations:
#         {$nameConst}:
#             config_path: '%kernel.project_dir%/src/Integration/{$name}/config/{$nameConst}.yaml'
#             base_url: '%env({$name}_BASE_URL)%'
#             # client_service: {$ns}\\{$name}HttpClient  # optional custom client

{$actionKey}:
    action: {$actionClass}
    method: POST   # TODO: set the correct HTTP method
    path: /TODO    # TODO: set the correct path
    # authorization:
    #   type: bearer
    #   token: '%env({$name}_API_KEY)%'
    #
    # --- OR dynamic auth (e.g. JWT) ---
    #   type: dynamic
    #   action: login
    #   token_field: token
    #   ttl: 3600
YAML;
    }

    private function writeFile(string $filePath, string $content, SymfonyStyle $io): void
    {
        $dir = dirname($filePath);

        if (!is_dir($dir) && !mkdir($dir, 0755, recursive: true) && !is_dir($dir)) {
            $io->error(sprintf('Could not create directory: %s', $dir));
            return;
        }

        if (file_exists($filePath)) {
            $io->warning(sprintf('Skipped (already exists): %s', $filePath));
            return;
        }

        file_put_contents($filePath, $content);
        $io->text(sprintf('  <info>created</info>  %s', $filePath));
    }

    private function printNextSteps(SymfonyStyle $io, string $name, string $action, string $ns, string $path): void
    {
        $nameConst = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
        $clientFqn = sprintf('%s\\%sHttpClient', $ns, $name);

        $io->success(sprintf('%d files generated.', 7));
        $io->section('Next steps');
        $io->listing([
            sprintf('Fill in the request fields in  <comment>%s/Body/%sBody.php</comment>', $path, $action),
            sprintf('Fill in the response fields in <comment>%s/Response/%sResponse.php</comment>', $path, $action),
            sprintf('Fill in the mapping in         <comment>%s/Mapper/%sMapper.php</comment>', $path, $action),
            sprintf('Set <comment>method</comment> and <comment>path</comment> in <comment>%s/config/%s.yaml</comment>', $path, strtolower($name)),
            sprintf(
                "Register in <comment>config/packages/integration_engine.yaml</comment>:\n\n" .
                "    integration_engine:\n" .
                "        integrations:\n" .
                "            %s:\n" .
                "                config_path: '%%kernel.project_dir%%/src/Integration/%s/config/%s.yaml'\n" .
                "                base_url: '%%env(%s_BASE_URL)%%'\n" .
                "                # client_service: %s",
                $nameConst, $name, $nameConst, strtoupper($name), $clientFqn,
            ),
            sprintf(
                "Use it:\n\n" .
                "    \$this->registry\n" .
                "        ->get(%sIntegration::NAME)\n" .
                "        ->send(%sAction::getName(), \$body);",
                $name, $action,
            ),
        ]);
    }
}
