<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\ResponseInterface;

final class FakeProtectedResponse implements ResponseInterface
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [];
    }
}

final class FakeProtectedAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'fake_get_protected';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): string
    {
        return FakeProtectedMapper::class;
    }
}
