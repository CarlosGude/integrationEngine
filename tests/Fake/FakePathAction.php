<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use IntegrationEngine\Core\Contract\AbstractAction;

final class FakePathAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'fake_path_action';
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
