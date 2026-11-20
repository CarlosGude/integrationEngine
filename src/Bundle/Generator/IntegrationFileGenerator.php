<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator;

use IntegrationEngine\Bundle\Exception\IntegrationGeneratorException;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use Symfony\Component\Yaml\Yaml;

/** @internal */
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

    public function isActionDeclared(IntegrationContext $ctx): bool
    {
        $configPath = $this->configPath($ctx);

        if (!file_exists($configPath)) {
            return false;
        }

        try {
            $yaml = Yaml::parseFile($configPath, Yaml::PARSE_CUSTOM_TAGS);

            return \is_array($yaml) && \array_key_exists($ctx->action, $yaml);
        } catch (\Throwable) {
            // A file that no longer parses (e.g. already holding a duplicate key) is still checked line by line.
            return 1 === preg_match('/^'.preg_quote($ctx->action, '/').':/m', (string) file_get_contents($configPath));
        }
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
        $integration = $this->registeredIntegration($bundleConfigPath, $integrationName);

        return \is_array($integration) && \is_string($integration['client'] ?? null)
            ? $integration['client']
            : SymfonyHttpClientAdapter::CLIENT_TYPE;
    }

    public function isRegistered(string $bundleConfigPath, string $integrationName): bool
    {
        return \is_array($this->registeredIntegration($bundleConfigPath, $integrationName));
    }

    /**
     * Writes the initial integration_engine.yaml bundle config.
     *
     * @param string $integrationsPath project-relative directory holding the integrations, as given to --path
     *
     * @throws \RuntimeException if the config directory cannot be created
     */
    public function createBundleConfig(
        string $configPath,
        string $integrationName,
        string $baseUrl,
        string $clientType,
        string $integrationsPath = 'src/Infrastructure/Integrations',
    ): void {
        $dir = \dirname($configPath);

        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw IntegrationGeneratorException::cannotCreateDirectory($dir);
        }

        if (false === file_put_contents($configPath, $this->bundleConfig($integrationName, $baseUrl, $clientType, $integrationsPath))) {
            throw IntegrationGeneratorException::cannotWriteFile($configPath);
        }
    }

    /**
     * The integration_engine.yaml content that registers one integration —
     * also shown to the user when an existing bundle config must be edited by hand.
     */
    public function bundleConfig(
        string $integrationName,
        string $baseUrl,
        string $clientType,
        string $integrationsPath = 'src/Infrastructure/Integrations',
    ): string {
        $integrationConfig = ['base_url' => $baseUrl];
        if (SymfonyHttpClientAdapter::CLIENT_TYPE !== $clientType) {
            $integrationConfig['client'] = $clientType;
        }
        $integrationsPath = trim($integrationsPath, '/');
        $integrationConfig['config_path'] = "%kernel.project_dir%/{$integrationsPath}/{$integrationName}/{$integrationName}.yaml";

        return Yaml::dump(
            ['integration_engine' => ['integrations' => [IntegrationContext::toSnakeCase($integrationName) => $integrationConfig]]],
            6,
            4,
        );
    }

    public function integrationExists(IntegrationContext $ctx): bool
    {
        return is_dir($this->root($ctx));
    }

    public function configPath(IntegrationContext $ctx): string
    {
        return $this->root($ctx).'/'.$ctx->name.'.yaml';
    }

    private function registeredIntegration(string $bundleConfigPath, string $integrationName): mixed
    {
        if (!file_exists($bundleConfigPath)) {
            return null;
        }

        try {
            $yaml = Yaml::parseFile($bundleConfigPath, Yaml::PARSE_CUSTOM_TAGS);
        } catch (\Throwable) {
            return null;
        }

        $engine = \is_array($yaml) ? ($yaml['integration_engine'] ?? null) : null;
        $integrations = \is_array($engine) ? ($engine['integrations'] ?? null) : null;

        return \is_array($integrations) ? ($integrations[IntegrationContext::toSnakeCase($integrationName)] ?? null) : null;
    }

    private function root(IntegrationContext $ctx): string
    {
        return $ctx->basePath;
    }
}
