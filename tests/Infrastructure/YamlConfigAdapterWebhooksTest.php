<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\SignatureType;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;
use IntegrationEngine\Infrastructure\Adapter\YamlConfigAdapter;
use IntegrationEngine\Tests\Fake\FakePathAction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class YamlConfigAdapterWebhooksTest extends TestCase
{
    /** @var list<string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            unlink($path);
        }
    }

    public function testParsesDefinitionAndReservesWebhooksKey(): void
    {
        $adapter = $this->adapter($this->definition());
        $definition = $adapter->getWebhookDefinition();
        self::assertNotNull($definition);
        self::assertSame('data.type', $definition->typeField);
        self::assertSame('id', $definition->idField);
        self::assertSame(YamlWebhookMapper::class, $definition->mapperFor('created'));
        self::assertSame(SignatureType::HmacSha256, $definition->signature->type);
        self::assertSame('GET', $adapter->getAction('test')->getMethod());
    }

    public function testReturnsNullWithoutWebhookSection(): void
    {
        self::assertNull($this->adapter(null)->getWebhookDefinition());
    }

    public function testContainerResolvedSecretOverridesYamlPlaceholder(): void
    {
        $definition = $this->definition();
        $definition['signature'] = ['type' => 'hmac_sha256', 'header' => 'X-Signature', 'secret' => '%env(TEST_SECRET)%'];
        $adapter = $this->adapter($definition, 'resolved-secret');
        self::assertSame('resolved-secret', $adapter->getWebhookDefinition()?->signature->secret);
    }

    /** @param array<string, mixed> $override */
    #[DataProvider('provideRejectsInvalidDefinitionCases')]
    public function testRejectsInvalidDefinition(array $override): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->adapter(array_replace($this->definition(), $override))->getWebhookDefinition();
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function provideRejectsInvalidDefinitionCases(): iterable
    {
        yield 'missing mapper' => [['events' => ['created' => []]]];

        yield 'missing class' => [['events' => ['created' => ['mapper' => 'NotExistingMapper']]]];

        yield 'wrong parent' => [['events' => ['created' => ['mapper' => \stdClass::class]]]];

        yield 'wrong event type' => [['events' => ['other' => ['mapper' => YamlWebhookMapper::class]]]];

        yield 'empty path' => [['type_field' => '']];

        yield 'invalid path' => [['id_field' => 'data..id']];

        yield 'unknown policy' => [['unknown_events' => 'discard']];

        yield 'unknown signature' => [['signature' => ['type' => 'unknown', 'header' => 'X', 'secret' => 's']]];

        yield 'timestamp requires tolerance' => [['signature' => ['type' => 'timestamped_hmac', 'header' => 'X', 'secret' => 's']]];

        yield 'hmac rejects tolerance' => [['signature' => ['type' => 'hmac_sha256', 'header' => 'X', 'secret' => 's', 'tolerance' => 300]]];
    }

    /** @return array<string, mixed> */
    private function definition(): array
    {
        return ['type_field' => 'data.type', 'id_field' => 'id', 'signature' => ['type' => 'hmac_sha256', 'header' => 'X-Signature', 'secret' => 'test-secret'], 'events' => ['created' => ['mapper' => YamlWebhookMapper::class]]];
    }

    /** @param null|array<string, mixed> $webhooks */
    private function adapter(?array $webhooks, ?string $secret = null): YamlConfigAdapter
    {
        $path = tempnam(sys_get_temp_dir(), 'v8-webhook-');
        self::assertNotFalse($path);
        $this->paths[] = $path;
        $config = ['test' => ['action' => FakePathAction::class, 'method' => 'GET', 'path' => '/']];
        if (null !== $webhooks) {
            $config['webhooks'] = $webhooks;
        }
        file_put_contents($path, Yaml::dump($config, 8));

        return new YamlConfigAdapter($path, $secret);
    }
}

final class YamlWebhookMapper extends AbstractWebhookMapper
{
    public static function eventType(): string
    {
        return 'created';
    }

    protected static function transform(array $payload, array $headers): WebhookEventInterface
    {
        return new YamlWebhookEvent();
    }
}
final readonly class YamlWebhookEvent implements WebhookEventInterface {}
