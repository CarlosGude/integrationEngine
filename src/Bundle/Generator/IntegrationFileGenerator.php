<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator;

final class IntegrationFileGenerator
{
    public function generate(IntegrationContext $ctx): array
    {
        $tpl = new TemplateRenderer($ctx);

        $root = $ctx->basePath . '/' . $ctx->name;
        $action = $ctx->action;

        return [
            /*
             * =========================
             * INTEGRATION ROOT
             * =========================
             */
            "{$root}/{$ctx->name}Integration.php" =>
                $tpl->integration(),

            "{$root}/{$ctx->name}HttpClient.php" =>
                $tpl->client(),

            "{$root}/config/" . strtolower($ctx->name) . ".yaml" =>
                $tpl->yaml(),

            /*
             * =========================
             * ACTION LAYER (ROOT PER ACTION)
             * =========================
             */
            "{$root}/{$action}/Request/{$action}Action.php" =>
                $tpl->action(),

            "{$root}/{$action}/Request/{$action}Body.php" =>
                $tpl->body(),

            "{$root}/{$action}/Response/{$action}Mapper.php" =>
                $tpl->mapper(),

            "{$root}/{$action}/Response/{$action}Response.php" =>
                $tpl->response(),
        ];
    }
}