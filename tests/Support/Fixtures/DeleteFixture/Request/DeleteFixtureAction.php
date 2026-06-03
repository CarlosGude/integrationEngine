<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Support\Fixtures\DeleteFixture\Request;

use IntegrationEngine\Core\Contract\AbstractAction;

final class DeleteFixtureAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'delete_fixture';
    }


    public static function hasResponse(): bool
    {
        return false;
    }

    public static function mapper(): ?string
    {
        return null;
    }
}
