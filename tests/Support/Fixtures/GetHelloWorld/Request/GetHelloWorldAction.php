<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Support\Fixtures\GetHelloWorld\Request;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Tests\Support\Fixtures\GetHelloWorld\Response\GetHelloWorldMapper;

final class GetHelloWorldAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'get_hello_world';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): ?string
    {
        return GetHelloWorldMapper::class;
    }
}
