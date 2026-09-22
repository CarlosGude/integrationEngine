<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\DependencyInjection\Compiler;

use IntegrationEngine\Bundle\Exception\IntegrationConfigurationException;
use IntegrationEngine\Core\Contract\Webhook\SignatureType;
use IntegrationEngine\Core\Contract\Webhook\WebhookDefinition;
use IntegrationEngine\Core\Webhook\Base64HmacSignatureVerifier;
use IntegrationEngine\Core\Webhook\HmacSha256SignatureVerifier;
use IntegrationEngine\Core\Webhook\TimestampedHmacSignatureVerifier;
use IntegrationEngine\Infrastructure\Adapter\YamlConfigAdapter;
use IntegrationEngine\Infrastructure\Clock\SystemClock;
use IntegrationEngine\Infrastructure\Webhook\IntegrationWebhookRequestParser;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Webhook\Client\AbstractRequestParser;

final readonly class WebhookWiring
{
    /** @param \Closure(string): bool $classExists */
    public function __construct(private \Closure $classExists) {}

    public function register(ContainerBuilder $container, string $name, string $path, string $configId): void
    {
        if (!is_file($path)) {
            return;
        }
        $definition = (new YamlConfigAdapter($path))->getWebhookDefinition();
        if (null === $definition) {
            return;
        }
        if (!(($this->classExists)(AbstractRequestParser::class))) {
            throw IntegrationConfigurationException::webhooksRequireSymfonyWebhook($name);
        }
        // DI resolves %env()% at runtime rather than reading deployment secrets
        // while compiling or asking the domain/YAML parser to read the environment.
        $container->getDefinition($configId)->setArgument(1, $definition->signature->secret);
        $runtimeDefinition = (new Definition(WebhookDefinition::class))
            ->setFactory([new Reference($configId), 'getWebhookDefinition']);
        $verifier = match ($definition->signature->type) {
            SignatureType::HmacSha256 => new Definition(HmacSha256SignatureVerifier::class),
            SignatureType::Base64Hmac => new Definition(Base64HmacSignatureVerifier::class),
            SignatureType::TimestampedHmac => new Definition(TimestampedHmacSignatureVerifier::class, [new Definition(SystemClock::class)]),
        };
        $container->setDefinition("integration_engine.webhook_parser.{$name}", (new Definition(IntegrationWebhookRequestParser::class, [
            $runtimeDefinition, $verifier, $name,
            new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]))->setPublic(true));
    }
}
