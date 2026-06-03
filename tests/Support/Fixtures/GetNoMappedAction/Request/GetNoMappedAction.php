<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Support\Fixtures\GetNoMappedAction\Request;

use IntegrationEngine\Core\Contract\AbstractAction;

final class GetNoMappedAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'get_not_mapped_action';
    }


    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): ?string
    {
        return null;
    }
}
