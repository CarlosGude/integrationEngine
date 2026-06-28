<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator;

use IntegrationEngine\Bundle\Exception\IntegrationGeneratorException;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use Symfony\Component\Yaml\Yaml;

final class IntegrationFileGenerator
{
    /** @return array<string, string> */
    public function generateIntegrationFiles(IntegrationContext $ctx): array
    {
        $tpl = new TemplateRenderer($ctx);
        $root = $this->root($ctx);

        return [
            "{$root}/{$ctx->name}Integration.php" => $tpl->integration(),
        ];
    }

    /** @return array<string, string> */
    public function generateActionFiles(IntegrationContext $ctx): array
    {
        $tpl = new TemplateRenderer($ctx);
        $root = $this->root($ctx);
        $action = $ctx->action;

        $files = [];

        $files["{$root}/{$action}/Request/{$action}Action.php"] = $tpl->action();

        if ($ctx->hasResponse()) {
            $files["{$root}/{$action}/Response/{$action}Mapper.php"] = $tpl->mapper();
            $files["{$root}/{$action}/Response/{$action}Response.php"] = $tpl->response();
        }

        return $files;
    }

    public function appendActionToConfig(IntegrationContext $ctx): string
    {
        $tpl = new TemplateRenderer($ctx);
        $configPath = $this->configPath($ctx);

        $entry = $tpl->yamlEntry();

        if (file_exists($configPath)) {
            file_put_contents($configPath, PHP_EOL.$entry, FILE_APPEND);
        } else {
            file_put_contents($configPath, $entry);
        }

        return $configPath;
    }

    public function detectClientType(string $bundleConfigPath, string $integrationName): string
    {
        if (!file_exists($bundleConfigPath)) {
            return SymfonyHttpClientAdapter::CLIENT_TYPE;
        }

        try {
            return $this->resolveClientType(Yaml::parseFile($bundleConfigPath), IntegrationContext::toSnakeCase($integrationName));
        } catch (\Throwable) {
            return SymfonyHttpClientAdapter::CLIENT_TYPE;
        }
    }

    /**
     * Writes the initial integration_engine.yaml bundle config.
     *
     * @throws \RuntimeException if the config directory cannot be created
     */
    public function createBundleConfig(
        string $configPath,
        string $integrationName,
        string $baseUrl,
        string $clientType,
    ): void {
        $dir = \dirname($configPath);

        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw IntegrationGeneratorException::cannotCreateDirectory($dir);
        }

        $snakeName = IntegrationContext::toSnakeCase($integrationName);

        $integrationConfig = ['base_url' => $baseUrl];
        if (SymfonyHttpClientAdapter::CLIENT_TYPE !== $clientType) {
            $integrationConfig['client'] = $clientType;
        }
        $integrationConfig['config_path'] = "%kernel.project_dir%/src/Infrastructure/Integrations/{$integrationName}/{$integrationName}.yaml";

        $content = Yaml::dump(
            ['integration_engine' => ['integrations' => [$snakeName => $integrationConfig]]],
            6,
            4,
        );

        if (false === file_put_contents($configPath, $content)) {
            throw IntegrationGeneratorException::cannotWriteFile($configPath);
        }
    }

    public function integrationExists(IntegrationContext $ctx): bool
    {
        return is_dir($this->root($ctx));
    }

    public function configPath(IntegrationContext $ctx): string
    {
        return $this->root($ctx).'/'.$ctx->name.'.yaml';
    }

    private function resolveClientType(mixed $yaml, string $snakeName): string
    {
        $engine = \is_array($yaml) ? ($yaml['integration_engine'] ?? null) : null;
        $integrations = \is_array($engine) ? ($engine['integrations'] ?? null) : null;
        $integration = \is_array($integrations) ? ($integrations[$snakeName] ?? null) : null;

        return \is_array($integration) && \is_string($integration['client'] ?? null)
            ? $integration['client']
            : SymfonyHttpClientAdapter::CLIENT_TYPE;
    }

    private function root(IntegrationContext $ctx): string
    {
        return $ctx->basePath;
    }
}
