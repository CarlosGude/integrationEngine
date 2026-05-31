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

    /* =========================
     * REQUEST LAYER
     * ========================= */

    public function action(): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$this->ctx->requestNamespace()};

use IntegrationEngine\Core\Contract\AbstractAction;
use {$this->ctx->responseNamespace()}\\{$this->ctx->action}Mapper;

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

namespace {$this->ctx->requestNamespace()};

use IntegrationEngine\Core\Contract\ActionBodyInterface;

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

    public function mapper(): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$this->ctx->responseNamespace()};

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\AbstractMapper;
use IntegrationEngine\Core\Contract\ResponseInterface;
use {$this->ctx->requestNamespace()}\\{$this->ctx->action}Action;
use {$this->ctx->responseNamespace()}\\{$this->ctx->action}Response;

final class {$this->ctx->action}Action extends AbstractAction
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

namespace {$this->ctx->responseNamespace()};

use IntegrationEngine\Core\Contract\ResponseInterface;

final readonly class {$this->ctx->action}Response implements ResponseInterface
{
    public function toArray(): array
    {
        return [];
    }
}
PHP;
    }

    /* =========================
     * CLIENT
     * ========================= */

    public function client(): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$this->ctx->integrationNamespace()};

use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;

final class {$this->ctx->name}HttpClient extends SymfonyHttpClientAdapter
{
}
PHP;
    }

    /* =========================
     * CONFIG
     * ========================= */

    public function yaml(): string
    {
        return <<<YAML
{$this->ctx->action}:
    action: {$this->ctx->requestNamespace()}\\{$this->ctx->action}Action
    method: POST
    path: /TODO
YAML;
    }
}