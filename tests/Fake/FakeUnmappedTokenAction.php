<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use IntegrationEngine\Core\Contract\Action\AbstractAction;

final class FakeUnmappedTokenAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'fake_unmapped_token';
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
