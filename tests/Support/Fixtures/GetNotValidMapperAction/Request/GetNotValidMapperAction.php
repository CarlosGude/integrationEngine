<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Support\Fixtures\GetNotValidMapperAction\Request;

use IntegrationEngine\Core\Contract\AbstractAction;

final class GetNotValidMapperAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'get_not_valid_mapped_action';
    }
    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): ?string
    {
        return 'not_valid_mapper';
    }
}
