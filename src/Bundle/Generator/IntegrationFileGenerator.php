<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator;

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

    public static function toSnakeCase(string $value): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/u', '_$0', $value) ?? $value);
    }

    public function detectClientType(string $bundleConfigPath, string $integrationName): string
    {
        if (!file_exists($bundleConfigPath)) {
            return 'rest';
        }

        try {
            /** @var array<string, mixed> $yaml */
            $yaml = Yaml::parseFile($bundleConfigPath);
            $snakeName = self::toSnakeCase($integrationName);

            $engine = $yaml['integration_engine'] ?? null;
            if (!\is_array($engine)) {
                return 'rest';
            }
            $integrations = $engine['integrations'] ?? null;
            if (!\is_array($integrations)) {
                return 'rest';
            }
            $integration = $integrations[$snakeName] ?? null;
            if (!\is_array($integration)) {
                return 'rest';
            }

            return \is_string($integration['client'] ?? null) ? $integration['client'] : 'rest';
        } catch (\Throwable) {
            return 'rest';
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
            throw new \RuntimeException("Could not create config directory: {$dir}");
        }

        $snakeName = self::toSnakeCase($integrationName);

        $integrationConfig = ['base_url' => $baseUrl];
        if ('rest' !== $clientType) {
            $integrationConfig['client'] = $clientType;
        }
        $integrationConfig['config_path'] = "%kernel.project_dir%/src/Infrastructure/Integrations/{$integrationName}/{$integrationName}.yaml";

        $content = Yaml::dump(
            ['integration_engine' => ['integrations' => [$snakeName => $integrationConfig]]],
            6,
            4,
        );

        if (false === file_put_contents($configPath, $content)) {
            throw new \RuntimeException("Could not write config file: {$configPath}");
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

    private function root(IntegrationContext $ctx): string
    {
        return $ctx->basePath;
    }
}
