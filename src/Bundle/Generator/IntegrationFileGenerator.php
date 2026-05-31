<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator;

final class IntegrationFileGenerator
{
    public function generate(IntegrationContext $ctx): array
    {
        $tpl = new TemplateRenderer($ctx);

        $ns = $ctx->integrationNamespace();
        $path = $ctx->integrationPath();

        return [
            "{$path}/{$ctx->name}Integration.php" =>
                $tpl->integration(),

            "{$path}/{$ctx->action}/{$ctx->action}Action.php" =>
                $tpl->action(),

            "{$path}/{$ctx->action}/{$ctx->action}Body.php" =>
                $tpl->body(),

            "{$path}/{$ctx->action}/{$ctx->action}Mapper.php" =>
                $tpl->mapper(),

            "{$path}/{$ctx->action}/{$ctx->action}Response.php" =>
                $tpl->response(),

            "{$path}/{$ctx->name}HttpClient.php" =>
                $tpl->client(),

            "{$path}/config/" . strtolower($ctx->name) . ".yaml" =>
                $tpl->yaml(),
        ];
    }
}