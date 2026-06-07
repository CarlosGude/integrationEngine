<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator;

final readonly class TemplateRenderer
{
    public function __construct(
        private IntegrationContext $ctx,
    ) {}

    /* =========================
     * INTEGRATION ROOT
     * ========================= */

    public function integration(): string
    {
        $name = $this->toSnakeCase($this->ctx->name);

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$this->ctx->integrationNamespace()};

            use IntegrationEngine\\Core\\Registry\\IntegrationName;

            final class {$this->ctx->name}Integration implements IntegrationName
            {
                public const string NAME = '{$name}';
            }
            PHP;
    }

    /**
     * A single action entry — appended to the yaml on every new action.
     */
    public function yamlEntry(): string
    {
        $actionFqcn = $this->ctx->requestNamespace().'\\'.$this->ctx->action.'Action';

        if (!$this->ctx->adapterRequiresPath && !$this->ctx->adapterRequiresMethod) {
            return "{$this->ctx->action}:\n    action: {$actionFqcn}\n";
        }

        $method = $this->ctx->adapterRequiresMethod ? strtoupper($this->ctx->method) : 'POST';
        $path = $this->ctx->adapterRequiresPath ? $this->ctx->path : '/';

        return "{$this->ctx->action}:\n    action: {$actionFqcn}\n    method: {$method}\n    path: {$path}\n";
    }

    /* =========================
     * REQUEST LAYER
     * ========================= */

    public function action(): string
    {
        $hasResponse = $this->ctx->hasResponse() ? 'true' : 'false';
        $mapperLine = $this->ctx->hasResponse()
            ? "return {$this->ctx->action}Mapper::class;"
            : 'return null;';

        $mapperUse = $this->ctx->hasResponse()
            ? "\nuse {$this->ctx->responseNamespace()}\\{$this->ctx->action}Mapper;"
            : '';

        $graphqlBodyUse = $this->ctx->needsGraphQLBodyHint()
            ? "\nuse IntegrationEngine\\Core\\Contract\\GraphQLBodyInterface;"
            : '';

        $graphqlBodyHint = $this->ctx->needsGraphQLBodyHint()
            ? "\n    // Attach a GraphQLBodyInterface implementation when calling send()."
            : '';

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$this->ctx->requestNamespace()};

            use IntegrationEngine\\Core\\Contract\\AbstractAction;{$mapperUse}{$graphqlBodyUse}

            final class {$this->ctx->action}Action extends AbstractAction
            {{$graphqlBodyHint}
                public static function getName(): string
                {
                    return '{$this->ctx->action}';
                }

                public static function hasResponse(): bool
                {
                    return {$hasResponse};
                }

                public static function mapper(): ?string
                {
                    {$mapperLine}
                }
            }
            PHP;
    }

    /* =========================
     * RESPONSE LAYER
     * ========================= */

    /**
     * Only generated when the action has a response (not DELETE).
     */
    public function mapper(): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$this->ctx->responseNamespace()};

            use IntegrationEngine\\Core\\Contract\\AbstractAction;
            use IntegrationEngine\\Core\\Contract\\AbstractMapper;
            use IntegrationEngine\\Core\\Contract\\ResponseInterface;
            use {$this->ctx->requestNamespace()}\\{$this->ctx->action}Action;

            final class {$this->ctx->action}Mapper extends AbstractMapper
            {
                public static function getAction(): string
                {
                    return {$this->ctx->action}Action::class;
                }

                protected static function transform(AbstractAction \$action, array \$response): ResponseInterface
                {
                    return new {$this->ctx->action}Response();
                }
            }
            PHP;
    }

    /**
     * Only generated when the action has a response (not DELETE).
     */
    public function response(): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$this->ctx->responseNamespace()};

            use IntegrationEngine\\Core\\Contract\\ResponseInterface;

            final readonly class {$this->ctx->action}Response implements ResponseInterface
            {
                public function toArray(): array
                {
                    return [];
                }
            }
            PHP;
    }

    private function toSnakeCase(string $value): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/u', '_$0', $value) ?? $value);
    }
}