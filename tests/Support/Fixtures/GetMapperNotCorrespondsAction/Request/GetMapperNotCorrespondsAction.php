<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Support\Fixtures\GetMapperNotCorrespondsAction\Request;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Tests\Support\Fixtures\GetHelloWorld\Response\GetHelloWorldMappper;

final readonly class GetMapperNotCorrespondsAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'get_mapper_not_corresponds_with_the_action';
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
        return GetHelloWorldMappper::class;
    }
}
