<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use IntegrationEngine\Core\Contract\Action\AbstractAction;

final class FakeSlowAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'fake_slow_action';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): string
    {
        return FakeSlowMapper::class;
    }
}
