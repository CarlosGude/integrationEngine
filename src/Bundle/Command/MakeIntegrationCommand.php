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
            ->addArgument('name', InputArgument::REQUIRED)
            ->addArgument('action', InputArgument::REQUIRED)
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'Base namespace', 'App\\Infrastructure\\Integrations')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Base path', 'src/Infrastructure/Integrations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $name */
        $name = (string) $input->getArgument('name');

        /** @var string $action */
        $action = (string) $input->getArgument('action');

        $namespace = rtrim((string) $input->getOption('namespace'), '\\');
        $basePath = rtrim((string) $input->getOption('path'), '/');

        $integrationNamespace = sprintf('%s\\%s', $namespace, $name);
        $integrationPath = sprintf('%s/%s/%s', $this->projectDir, $basePath, $name);

        $io->title(sprintf('Generating integration: %s', $name));

        $files = $this->buildFiles($name, $action, $integrationNamespace, $integrationPath);

        foreach ($files as $filePath => $content) {
            $this->writeFile($filePath, $content, $io);
        }

        $this->printNextSteps($io, $name, $action, $integrationNamespace, $integrationPath);

        return Command::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function buildFiles(
        string $name,
        string $action,
        string $ns,
        string $path,
    ): array {
        return [
            sprintf('%s/%sIntegration.php', $path, $name)
            => $this->renderIntegration($name, $ns),

            sprintf('%s/%s/%sAction.php', $path, $action, $action)
            => $this->renderAction($action, $ns),

            sprintf('%s/%s/%sBody.php', $path, $action, $action)
            => $this->renderBody($action, $ns),

            sprintf('%s/%s/%sMapper.php', $path, $action, $action)
            => $this->renderMapper($action, $ns),

            sprintf('%s/%s/%sResponse.php', $path, $action, $action)
            => $this->renderResponse($action, $ns),

            sprintf('%s/%sHttpClient.php', $path, $name)
            => $this->renderClient($name, $ns),

            sprintf('%s/config/%s.yaml', $path, strtolower((string) $name))
            => $this->renderYaml($name, $action, $ns),
        ];
    }

    private function renderIntegration(string $name, string $ns): string
    {
        $constValue = (string) strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name) ?? '');

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

namespace {$ns}\\Actions;

use IntegrationEngine\Core\\Contract\\AbstractAction;
use {$ns}\\Mappers\\{$action}Mapper;

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

namespace {$ns}\\Body;

use IntegrationEngine\Core\\Contract\\ActionBodyInterface;

final readonly class {$action}Body implements ActionBodyInterface
{
    public function __construct(
    ) {
    }

    public function toArray(): array
    {
        return [];
    }
}
PHP;
    }

    private function renderMapper(string $action, string $ns): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns}\\Mappers;

use IntegrationEngine\Core\\Contract\\AbstractAction;
use IntegrationEngine\Core\\Contract\\AbstractMapper;
use IntegrationEngine\Core\\Contract\\ResponseInterface;
use {$ns}\\Actions\\{$action}Action;
use {$ns}\\Responses\\{$action}Response;

final class {$action}Mapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return {$action}Action::class;
    }

    protected static function transform(AbstractAction \$action, array \$response): ResponseInterface
    {
        return new {$action}Response();
    }
}
PHP;
    }

    private function renderResponse(string $action, string $ns): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns}\\Responses;

use IntegrationEngine\Core\\Contract\\ResponseInterface;

final readonly class {$action}Response implements ResponseInterface
{
    public function __construct(
    ) {
    }

    public function toArray(): array
    {
        return [];
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

use IntegrationEngine\\Infrastructure\\Http\\SymfonyHttpClientAdapter;

final class {$name}HttpClient extends SymfonyHttpClientAdapter
{
}
PHP;
    }

    private function renderYaml(string $name, string $action, string $ns): string
    {
        $actionClass = sprintf('%s\\Actions\\%sAction', $ns, $action);
        $actionKey = lcfirst($action);

        $nameConst = (string) strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name) ?? '');

        return <<<YAML
{$actionKey}:
    action: {$actionClass}
    method: POST
    path: /TODO
YAML;
    }

    private function writeFile(string $filePath, string $content, SymfonyStyle $io): void
    {
        $dir = dirname($filePath);

        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            $io->error(sprintf('Cannot create dir: %s', $dir));
            return;
        }

        if (file_exists($filePath)) {
            $io->warning(sprintf('Skipped: %s', $filePath));
            return;
        }

        file_put_contents($filePath, $content);
        $io->text(sprintf('created %s', $filePath));
    }

    private function printNextSteps(
        SymfonyStyle $io,
        string $name,
        string $action,
        string $ns,
        string $path,
    ): void {
        $io->success('Done.');
    }
}