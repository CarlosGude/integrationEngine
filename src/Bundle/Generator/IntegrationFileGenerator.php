<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator;

final class IntegrationFileGenerator
{
    public function generate(IntegrationContext $ctx): array
    {
        $tpl = new TemplateRenderer($ctx);

        $base = $ctx->basePath;
        $action = $ctx->action;

        return [
            // Integration root
            "{$base}/{$ctx->name}Integration.php" =>
                $tpl->integration(),

            // ACTION ROOT FOLDER + REQUEST
            "{$base}/{$action}/Request/{$action}Action.php" =>
                $tpl->action(),

            "{$base}/{$action}/Request/{$action}Body.php" =>
                $tpl->body(),

            // RESPONSE LAYER
            "{$base}/{$action}/Response/{$action}Mapper.php" =>
                $tpl->mapper(),

            "{$base}/{$action}/Response/{$action}Response.php" =>
                $tpl->response(),

            // CLIENT
            "{$base}/{$ctx->name}HttpClient.php" =>
                $tpl->client(),

            // CONFIG
            "{$base}/config/" . strtolower($ctx->name) . ".yaml" =>
                $tpl->yaml(),
        ];
    }
}