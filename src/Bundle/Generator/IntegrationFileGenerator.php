<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator;

final class IntegrationFileGenerator
{
    /** @return array<string, string> */
    public function generateIntegrationFiles(IntegrationContext $ctx): array
    {
        $tpl = new TemplateRenderer($ctx);
        $root = $this->root($ctx);

        return [
            "{$root}/{$ctx->name}Integration.php" => $tpl->integration(),
            // HttpClient removed — the bundle's default client is used
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
