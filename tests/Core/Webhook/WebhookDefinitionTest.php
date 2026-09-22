<?php

declare(strict_types=1);
namespace IntegrationEngine\Tests\Core\Webhook;
use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\SignatureConfig;
use IntegrationEngine\Core\Contract\Webhook\SignatureType;
use IntegrationEngine\Core\Contract\Webhook\UnknownEventPolicy;
use IntegrationEngine\Core\Contract\Webhook\WebhookDefinition;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
final class WebhookDefinitionTest extends TestCase
{
    public function testSelectsMapperOrReturnsNull(): void
    {
        $definition = new WebhookDefinition('type','data.object.id',new SignatureConfig(SignatureType::HmacSha256,'x-signature','secret'),UnknownEventPolicy::Ignore,['event'=>DefinitionMapper::class]);
        self::assertSame(DefinitionMapper::class,$definition->mapperFor('event'));
        self::assertNull($definition->mapperFor('unknown'));
    }
    #[DataProvider('invalidPaths')]
    public function testRejectsMalformedPaths(string $type, string $id): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WebhookDefinition($type,$id,new SignatureConfig(SignatureType::HmacSha256,'x-signature','secret'),UnknownEventPolicy::Ignore,[]);
    }
    /** @return iterable<array{string,string}> */
    public static function invalidPaths(): iterable
    {
        yield ['','id']; yield ['type','']; yield ['.type','id']; yield ['type.','id']; yield ['type','data..id']; yield ['type','a b'];
    }
}
final class DefinitionMapper extends AbstractWebhookMapper
{
    public static function eventType(): string { return 'event'; }
    protected static function transform(array $payload,array $headers): WebhookEventInterface { return new DefinitionEvent(); }
}
final readonly class DefinitionEvent implements WebhookEventInterface {}
