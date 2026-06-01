<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Support\Fixtures\GetHelloWorldWithStaticAuthAction\Request;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Tests\Support\Fixtures\GetHelloWorldWithStaticAuthAction\Response\GetHelloWorldWithStaticAuthMapper;

final readonly class GetHelloWorldWithStaticAuthAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'get_hello_world';
    }

    public static function hasBody(): bool
    {
        return false;
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): ?string
    {
        return GetHelloWorldWithStaticAuthMapper::class;
    }
}
