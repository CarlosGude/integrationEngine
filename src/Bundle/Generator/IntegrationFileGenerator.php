<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator;

final class IntegrationFileGenerator
{
    public function generate(IntegrationContext $ctx): array
    {
        $tpl = new TemplateRenderer($ctx);

        $base = $ctx->basePath;

        return [
            // Integration root
            "{$base}/{$ctx->name}Integration.php" =>
                $tpl->integration(),

            // REQUEST LAYER
            "{$base}/Request/{$ctx->action}Action.php" =>
                $tpl->action(),

            "{$base}/Request/{$ctx->action}Body.php" =>
                $tpl->body(),

            // RESPONSE LAYER
            "{$base}/Response/{$ctx->action}Mapper.php" =>
                $tpl->mapper(),

            "{$base}/Response/{$ctx->action}Response.php" =>
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