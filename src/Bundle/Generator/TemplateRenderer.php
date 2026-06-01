<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator;

final class TemplateRenderer
{
    public function __construct(
        private readonly IntegrationContext $ctx,
    ) {}

    /* =========================
     * INTEGRATION ROOT
     * ========================= */

    public function integration(): string
    {
        $name = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $this->ctx->name));

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

    public function client(): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$this->ctx->integrationNamespace()};

            use IntegrationEngine\\Infrastructure\\Http\\SymfonyHttpClientAdapter;

            final readonly class {$this->ctx->name}HttpClient extends SymfonyHttpClientAdapter
            {
            }
            PHP;
    }

    /**
     * Full yaml file — only written when the integration is created for the first time.
     */
    public function yaml(): string
    {
        return $this->yamlEntry();
    }

    /**
     * A single action entry — appended to the yaml on every new action.
     */
    public function yamlEntry(): string
    {
        $method = strtoupper($this->ctx->method);
        $actionFqcn = $this->ctx->requestNamespace().'\\'.$this->ctx->action.'Action';

        return "{$this->ctx->action}:\n    action: {$actionFqcn}\n    method: {$method}\n    path: {$this->ctx->path}\n";
    }

    /* =========================
     * REQUEST LAYER
     * ========================= */

    public function action(): string
    {
        $hasBody = $this->ctx->hasBody() ? 'true' : 'false';
        $hasResponse = $this->ctx->hasResponse() ? 'true' : 'false';
        $mapperLine = $this->ctx->hasResponse()
            ? "return {$this->ctx->action}Mapper::class;"
            : 'return null;';

        $mapperUse = $this->ctx->hasResponse()
            ? "\nuse {$this->ctx->responseNamespace()}\\{$this->ctx->action}Mapper;"
            : '';

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$this->ctx->requestNamespace()};

            use IntegrationEngine\\Core\\Contract\\AbstractAction;{$mapperUse}

            final readonly class {$this->ctx->action}Action extends AbstractAction
            {
                public static function getName(): string
                {
                    return '{$this->ctx->action}';
                }

                public static function hasBody(): bool
                {
                    return {$hasBody};
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

    /**
     * Only generated for POST and PUT.
     */
    public function body(): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$this->ctx->requestNamespace()};

            use IntegrationEngine\\Core\\Contract\\ActionBodyInterface;

            final readonly class {$this->ctx->action}Body implements ActionBodyInterface
            {
                public function toArray(): array
                {
                    return [];
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
}
