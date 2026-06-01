<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator;

final class IntegrationFileGenerator
{
    /**
     * Root already includes the integration name.
     */
    private function root(IntegrationContext $ctx): string
    {
        return $ctx->basePath;
    }

    public function generateIntegrationFiles(IntegrationContext $ctx): array
    {
        $tpl  = new TemplateRenderer($ctx);
        $root = $this->root($ctx);

        return [
            "{$root}/{$ctx->name}Integration.php" => $tpl->integration(),
            "{$root}/{$ctx->name}HttpClient.php"  => $tpl->client(),
        ];
    }

    public function generateActionFiles(IntegrationContext $ctx): array
    {
        $tpl    = new TemplateRenderer($ctx);
        $root   = $this->root($ctx);
        $action = $ctx->action;

        $files = [];

        // Request
        $files["{$root}/{$action}/Request/{$action}Action.php"] = $tpl->action();

        if ($ctx->hasBody()) {
            $files["{$root}/{$action}/Request/{$action}Body.php"] = $tpl->body();
        }

        // Response
        if ($ctx->hasResponse()) {
            $files["{$root}/{$action}/Response/{$action}Mapper.php"]    = $tpl->mapper();
            $files["{$root}/{$action}/Response/{$action}Response.php"]  = $tpl->response();
        }

        return $files;
    }

    public function appendActionToConfig(IntegrationContext $ctx): string
    {
        $tpl        = new TemplateRenderer($ctx);
        $configPath = $this->configPath($ctx);

        $entry = $tpl->yamlEntry();

        if (file_exists($configPath)) {
            file_put_contents($configPath, PHP_EOL . $entry, FILE_APPEND);
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
        return $this->root($ctx) . '/' . $ctx->name . '.yaml';
    }
}