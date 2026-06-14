<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Action\DefaultActionContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DefaultActionContextTest extends TestCase
{
    #[Test]
    public function implementsActionContextInterface(): void
    {
        $context = DefaultActionContext::create([]);

        self::assertInstanceOf(ActionContextInterface::class, $context);
    }

    #[Test]
    public function toArrayReturnsConstructedData(): void
    {
        $data = ['id' => 42, 'slug' => 'employees', 'active' => true];

        $context = DefaultActionContext::create($data);

        self::assertSame($data, $context->toArray());
    }

    #[Test]
    public function toArrayReturnsEmptyArrayWhenCreatedEmpty(): void
    {
        $context = DefaultActionContext::create([]);

        self::assertSame([], $context->toArray());
    }
}
