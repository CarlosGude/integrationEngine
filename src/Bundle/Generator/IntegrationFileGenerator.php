<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator;

final class IntegrationFileGenerator
{
    /**
     * Files needed only once per integration (root level).
     * Returns an empty array if the integration already exists.
     */
    public function generateIntegrationFiles(IntegrationContext $ctx): array
    {
        $tpl  = new TemplateRenderer($ctx);
        $root = $ctx->basePath . '/' . $ctx->name;

        return [
            "{$root}/{$ctx->name}Integration.php" => $tpl->integration(),
            "{$root}/{$ctx->name}HttpClient.php"  => $tpl->client(),
            "{$root}/{$ctx->name}.yaml"            => $tpl->yaml(),
        ];
    }

    /**
     * Files generated for every new action, respecting the HTTP method rules:
     *
     *   GET    → Request/Action          + Response/Mapper + Response/Response
     *   POST   → Request/Action + Body   + Response/Mapper + Response/Response
     *   PUT    → Request/Action + Body   + Response/Mapper + Response/Response
     *   DELETE → Request/Action          (no Response layer)
     */
    public function generateActionFiles(IntegrationContext $ctx): array
    {
        $tpl    = new TemplateRenderer($ctx);
        $root   = $ctx->basePath . '/' . $ctx->name;
        $action = $ctx->action;

        $files = [];

        // ── Request layer ────────────────────────────────────────────────────
        $files["{$root}/{$action}/Request/{$action}Action.php"] = $tpl->action();

        if ($ctx->hasBody()) {
            $files["{$root}/{$action}/Request/{$action}Body.php"] = $tpl->body();
        }

        // ── Response layer (not for DELETE) ──────────────────────────────────
        if ($ctx->hasResponse()) {
            $files["{$root}/{$action}/Response/{$action}Mapper.php"]   = $tpl->mapper();
            $files["{$root}/{$action}/Response/{$action}Response.php"] = $tpl->response();
        }

        return $files;
    }

    /**
     * Appends the new action entry to the existing {Integration}.yaml config file.
     * If the file doesn't exist yet (shouldn't happen, but safe), it creates it.
     */
    public function appendActionToConfig(IntegrationContext $ctx): string
    {
        $tpl        = new TemplateRenderer($ctx);
        $configPath = $ctx->basePath . '/' . $ctx->name . '/' . $ctx->name . '.yaml';

        $entry = $tpl->yamlEntry();

        if (file_exists($configPath)) {
            file_put_contents($configPath, "\n" . $entry, FILE_APPEND);
        } else {
            file_put_contents($configPath, $entry);
        }

        return $configPath;
    }

    public function integrationExists(IntegrationContext $ctx): bool
    {
        return is_dir($ctx->basePath . '/' . $ctx->name);
    }

    public function configPath(IntegrationContext $ctx): string
    {
        return $ctx->basePath . '/' . $ctx->name . '/' . $ctx->name . '.yaml';
    }
}