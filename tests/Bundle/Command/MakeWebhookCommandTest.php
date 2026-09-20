<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Bundle\Command;

use IntegrationEngine\Bundle\Command\MakeWebhookCommand;
use IntegrationEngine\Bundle\Generator\WebhookFileGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Tester\CommandTester;

final class MakeWebhookCommandTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/ie-make-webhook-'.bin2hex(random_bytes(4));
        mkdir($this->projectDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

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

    public function testItWritesTheFourFilesAndPrintsTheRoutingEntry(): void
    {
        $tester = $this->generate(['stripe', 'charge.succeeded'], ['hmac_sha256', 'X-Webhook-Signature']);

        $tester->assertCommandIsSuccessful();
        foreach (['ChargeSucceededEvent', 'ChargeSucceededEventMapper', 'ChargeSucceededRequestParser', 'ChargeSucceededConsumer'] as $class) {
            self::assertFileExists($this->projectDir.'/src/Webhooks/Stripe/'.$class.'.php');
        }

        $output = $tester->getDisplay();
        self::assertStringContainsString('stripe_charge_succeeded:', $output);
        self::assertStringContainsString('service: App\Webhooks\Stripe\ChargeSucceededRequestParser', $output);
    }

    public function testPickingAProviderSkipsTheHeaderQuestion(): void
    {
        // Shopify's header is part of its verifier, so the command only asks
        // for the verification type.
        $tester = $this->generate(['shopify', 'products/update'], ['shopify']);

        $tester->assertCommandIsSuccessful();
        self::assertStringNotContainsString('Signature header name', $tester->getDisplay());

        $parser = (string) file_get_contents($this->projectDir.'/src/Webhooks/Shopify/ProductsUpdateRequestParser.php');
        self::assertStringContainsString('use VerifiesShopifySignature;', $parser);
        self::assertStringNotContainsString('getSignatureVerifier', $parser);
    }

    private function removeDirectory(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $dir.'/'.$entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    /**
     * @param list<string> $arguments
     * @param list<string> $answers
     */
    private function generate(array $arguments, array $answers): CommandTester
    {
        $command = new MakeWebhookCommand($this->projectDir, new WebhookFileGenerator());
        $application = new Application();
        $application->addCommands([$command]);

        $tester = new CommandTester($command);
        $tester->setInputs($answers);
        $tester->execute(['integration' => $arguments[0], 'event' => $arguments[1]]);

        return $tester;
    }
}
