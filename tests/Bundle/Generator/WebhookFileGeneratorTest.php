<?php

declare(strict_types=1);
namespace IntegrationEngine\Tests\Bundle\Generator;
use IntegrationEngine\Bundle\Generator\WebhookContext;
use IntegrationEngine\Bundle\Generator\WebhookFileGenerator;
use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class WebhookFileGeneratorTest extends TestCase
{
    private function context(string $event = 'charge.succeeded', string $namespace = 'Generated'): WebhookContext
    {
        return new WebhookContext('stripe', $event, 'timestamped_hmac', 'Stripe-Signature', $namespace, '/tmp/webhooks');
    }
    public function testGeneratedPhpLoadsAndMapsToReadonlyEvent(): void
    {
        $ctx = $this->context(namespace: 'Generated'.bin2hex(random_bytes(4)));
        $files = (new WebhookFileGenerator())->generateFiles($ctx);
        self::assertCount(2, $files);
        foreach ($files as $content) {
            eval(substr($content, 5));
        }
        $mapper = $ctx->namespace().'\\'.$ctx->mapperClassName();
        self::assertTrue(is_subclass_of($mapper, AbstractWebhookMapper::class));
        $event = $mapper::map('charge.succeeded', ['id' => 'evt'], []);
        self::assertSame($ctx->eventClassFqn(), $event::class);
        self::assertTrue((new \ReflectionClass($event))->isReadOnly());
        self::assertEquals($event, unserialize(serialize($event)));
        $this->expectException(\UnexpectedValueException::class);
        $mapper::map('charge.succeeded', ['id' => []], []);
    }
    public function testMergeKeepsActionsAndAddsTwoEventsWithoutReplacingExistingMapping(): void
    {
        $generator = new WebhookFileGenerator();
        $first = $generator->mergeYaml($this->context(), "GetThing:\n    method: GET\n");
        $second = $generator->mergeYaml($this->context('charge.failed'), $first);
        $config = Yaml::parse($second);
        self::assertIsArray($config);
        self::assertArrayHasKey('GetThing', $config);
        self::assertStringContainsString('charge.succeeded:', $second);
        self::assertStringContainsString('charge.failed:', $second);
        self::assertStringContainsString('tolerance: 300', $second);
        self::assertSame($second, $generator->mergeYaml($this->context(namespace: 'Other'), $second));
        self::assertStringContainsString('Other\\Stripe', $generator->mergeYaml($this->context(namespace: 'Other'), $second, true));
    }
    public function testRejectsMalformedExistingYaml(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new WebhookFileGenerator())->mergeYaml($this->context(), 'scalar');
    }
}
