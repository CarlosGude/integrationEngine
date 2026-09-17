<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Bundle\Command;

use IntegrationEngine\Bundle\Command\MakeWebhookCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Attribute\AsCommand;

final class MakeWebhookCommandTest extends TestCase
{
    public function testMakeWebhookCommandIsRegistered(): void
    {
        self::assertTrue(class_exists(MakeWebhookCommand::class));
    }

    public function testCommandHasCorrectName(): void
    {
        $reflection = new \ReflectionClass(MakeWebhookCommand::class);
        $attributes = $reflection->getAttributes(AsCommand::class);

        self::assertNotEmpty($attributes, 'MakeWebhookCommand should have #[AsCommand] attribute');
    }
}
