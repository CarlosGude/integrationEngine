<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Bundle\DependencyInjection;

use IntegrationEngine\Bundle\DependencyInjection\Compiler\IntegrationCompilerPass;
use IntegrationEngine\Bundle\Exception\IntegrationConfigurationException;
use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;
use IntegrationEngine\Core\Registry\IntegrationRegistry;
use IntegrationEngine\Core\Webhook\HmacSha256SignatureVerifier;
use IntegrationEngine\Core\Webhook\TimestampedHmacSignatureVerifier;
use IntegrationEngine\Infrastructure\Http\ClientAdapterResolver;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use IntegrationEngine\Bundle\DependencyInjection\Configuration;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Yaml\Yaml;

final class IntegrationCompilerPassWebhooksTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'webhook-di-');
        self::assertNotFalse($path);
        $this->path = $path;
    }

    protected function tearDown(): void { unlink($this->path); }

    public function testRegistersHmacParserAndRuntimeSecret(): void
    {
        $container = $this->container('hmac_sha256');
        (new IntegrationCompilerPass())->process($container);
        $parser = $container->getDefinition('integration_engine.webhook_parser.api');
        $verifier = $parser->getArgument(1);
        self::assertInstanceOf(Definition::class, $verifier);
        self::assertSame(HmacSha256SignatureVerifier::class, $verifier->getClass());
        self::assertSame('%env(WEBHOOK_SECRET)%', $container->getDefinition('integration_engine.config.api')->getArgument(1));
        self::assertSame('api', $parser->getArgument(2));
    }

    public function testRegistersTimestampVerifierWithClock(): void
    {
        $container = $this->container('timestamped_hmac');
        (new IntegrationCompilerPass())->process($container);
        $verifier = $container->getDefinition('integration_engine.webhook_parser.api')->getArgument(1);
        self::assertInstanceOf(Definition::class, $verifier);
        self::assertSame(TimestampedHmacSignatureVerifier::class, $verifier->getClass());
        self::assertInstanceOf(Definition::class, $verifier->getArgument(0));
    }

    public function testNoWebhookNoParser(): void
    {
        $container = $this->container(null);
        (new IntegrationCompilerPass())->process($container);
        self::assertFalse($container->hasDefinition('integration_engine.webhook_parser.api'));
    }

    public function testMissingOptionalDependencyHasClearMessage(): void
    {
        $container = $this->container('hmac_sha256');
        $this->expectException(IntegrationConfigurationException::class);
        $this->expectExceptionMessage('composer require symfony/webhook symfony/remote-event');
        (new IntegrationCompilerPass(static fn (string $class): bool => false))->process($container);
    }

    private function container(?string $type): ContainerBuilder
    {
        $yaml = [];
        if (null !== $type) {
            $signature = ['type' => $type, 'header' => 'X-Signature', 'secret' => '%env(WEBHOOK_SECRET)%'];
            if ('timestamped_hmac' === $type) { $signature['tolerance'] = 300; }
            $yaml['webhooks'] = ['type_field' => 'type', 'id_field' => 'id', 'signature' => $signature, 'events' => ['created' => ['mapper' => CompilerWebhookMapper::class]]];
        } else {
            $yaml['webhooks'] = [];
        }
        file_put_contents($this->path, Yaml::dump($yaml, 8));
        $container = new ContainerBuilder();
        $config = (new Processor())->processConfiguration(new Configuration(), [['integrations' => ['api' => ['config_path' => $this->path, 'base_url' => 'https://api.example']]]]);
        $container->setParameter('integration_engine.integrations', $config['integrations']);
        $container->setDefinition(IntegrationRegistry::class, new Definition(IntegrationRegistry::class));
        $container->setDefinition(ClientAdapterResolver::class, new Definition(ClientAdapterResolver::class));
        $container->setDefinition(SymfonyHttpClientAdapter::class, (new Definition(SymfonyHttpClientAdapter::class))->addTag('integration_engine.client_adapter'));

        return $container;
    }
}
final class CompilerWebhookMapper extends AbstractWebhookMapper
{
    public static function eventType(): string { return 'created'; }
    protected static function transform(array $payload, array $headers): WebhookEventInterface { return new CompilerWebhookEvent(); }
}
final readonly class CompilerWebhookEvent implements WebhookEventInterface {}
