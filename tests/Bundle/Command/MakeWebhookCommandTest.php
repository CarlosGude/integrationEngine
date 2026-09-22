<?php

declare(strict_types=1);
namespace IntegrationEngine\Tests\Bundle\Command;
use IntegrationEngine\Bundle\Command\MakeWebhookCommand;
use IntegrationEngine\Bundle\Generator\WebhookFileGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class MakeWebhookCommandTest extends TestCase
{
    public function testNonInteractiveGenerationMergesEventsAndPreservesFiles(): void
    {
        $dir = sys_get_temp_dir().'/webhook-v8-'.bin2hex(random_bytes(4));
        $command = new MakeWebhookCommand($dir, new WebhookFileGenerator());
        (new Application())->addCommands([$command]);
        $tester = new CommandTester($command);
        try {
            $tester->execute(['integration'=>'Stripe','event'=>'charge.succeeded'], ['interactive'=>false]);
            $tester->assertCommandIsSuccessful();
            $event = $dir.'/src/Webhooks/Stripe/ChargeSucceededEvent.php';
            self::assertFileExists($event);
            file_put_contents($event, 'preserved');
            $tester->execute(['integration'=>'Stripe','event'=>'charge.succeeded'], ['interactive'=>false]);
            self::assertSame('preserved', file_get_contents($event));
            $tester->execute(['integration'=>'Stripe','event'=>'charge.failed'], ['interactive'=>false]);
            $yaml = file_get_contents($dir.'/src/Webhooks/Stripe/Stripe.yaml');
            self::assertIsString($yaml);
            self::assertStringContainsString('charge.succeeded:', $yaml);
            self::assertStringContainsString('charge.failed:', $yaml);
            $tester->execute(['integration'=>'Stripe','event'=>'charge.succeeded','--force'=>true], ['interactive'=>false]);
            self::assertStringContainsString('final readonly class', (string) file_get_contents($event));
        } finally {
            foreach (glob($dir.'/src/Webhooks/Stripe/*') ?: [] as $file) { unlink($file); }
            foreach (['/src/Webhooks/Stripe','/src/Webhooks','/src',''] as $path) { if (is_dir($dir.$path)) { rmdir($dir.$path); } }
        }
    }
}
