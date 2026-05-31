<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Generator;

final class TemplateRenderer
{
    public function __construct(
        private readonly IntegrationContext $ctx,
    ) {
    }

    public function integration(): string
    {
        $name = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $this->ctx->name));

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$this->ctx->integrationNamespace()};

use IntegrationEngine\Core\Registry\IntegrationName;

final class {$this->ctx->name}Integration implements IntegrationName
{
    public const string NAME = '{$name}';
}
PHP;
    }

    public function action(): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$this->ctx->integrationNamespace()}\\Actions;

use IntegrationEngine\Core\\Contract\\AbstractAction;
use {$this->ctx->integrationNamespace()}\\Mappers\\{$this->ctx->action}Mapper;

final readonly class {$this->ctx->action}Action extends AbstractAction
{
    public static function getName(): string
    {
        return '{$this->ctx->action}';
    }

    public static function getMapper(): ?string
    {
        return {$this->ctx->action}Mapper::class;
    }
}
PHP;
    }

    public function body(): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$this->ctx->integrationNamespace()}\\Body;

use IntegrationEngine\Core\\Contract\\ActionBodyInterface;

final readonly class {$this->ctx->action}Body implements ActionBodyInterface
{
    public function toArray(): array
    {
        return [];
    }
}
PHP;
    }

    public function mapper(): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$this->ctx->integrationNamespace()}\\Mappers;

use IntegrationEngine\Core\\Contract\\AbstractAction;
use IntegrationEngine\Core\\Contract\\AbstractMapper;
use IntegrationEngine\Core\\Contract\\ResponseInterface;
use {$this->ctx->integrationNamespace()}\\Actions\\{$this->ctx->action}Action;
use {$this->ctx->integrationNamespace()}\\Responses\\{$this->ctx->action}Response;

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

    public function response(): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$this->ctx->integrationNamespace()}\\Responses;

use IntegrationEngine\Core\\Contract\\ResponseInterface;

final readonly class {$this->ctx->action}Response implements ResponseInterface
{
    public function toArray(): array
    {
        return [];
    }
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

final class {$this->ctx->name}HttpClient extends SymfonyHttpClientAdapter
{
}
PHP;
    }

    public function yaml(): string
    {
        return <<<YAML
{$this->ctx->action}:
    action: {$this->ctx->integrationNamespace()}\\Actions\\{$this->ctx->action}Action
    method: POST
    path: /TODO
YAML;
    }
}