<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Contract\Action\ActionBodyInterface;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Infrastructure\Adapter\YamlConfigAdapter;
use IntegrationEngine\Tests\Fake\FakeCache;
use IntegrationEngine\Tests\Fake\FakeClient;
use IntegrationEngine\Tests\Fake\FakeContext;
use IntegrationEngine\Tests\Fake\FakePathAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end coverage (real YamlConfigAdapter, not FakeConfigPort) for how
 * body-sourced and context-sourced path placeholders coexist: body is
 * resolved eagerly in ConfigPort::getAction(), before context even exists
 * in the call — so a placeholder present in the body is always settled by
 * the time AbstractAction::getPath() runs, and context is only ever
 * consulted for whatever the body left unresolved.
 */
final class PathPlaceholderPriorityTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/ie_path_priority_'.uniqid();
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->tmpDir);
    }

    #[Test]
    public function placeholderSuppliedOnlyByBodyIsResolved(): void
    {
        [$engine, $client] = $this->buildEngine();

        $engine->send('get_variations', body: PathPriorityTestBody::create(['product_id' => 123]));

        self::assertSame('/products/123/variations', $client->lastAction()?->getRawPath());
    }

    #[Test]
    public function placeholderSuppliedOnlyByContextIsResolved(): void
    {
        [$engine, $client] = $this->buildEngine();

        $engine->send('get_variations', context: FakeContext::create(['product_id' => 456]));

        // Unlike the body path (resolved eagerly in ConfigPort, baked into
        // getRawPath()), context is only resolved lazily by getPath() at
        // send time — the raw path still carries the placeholder here.
        self::assertSame('/products/{product_id}/variations', $client->lastAction()?->getRawPath());
        self::assertSame('/products/456/variations', $client->lastAction()?->getPath($client->lastContext()));
    }

    #[Test]
    public function bodyValueTakesPriorityOverContextWhenBothSupplyTheSameKey(): void
    {
        [$engine, $client] = $this->buildEngine();

        $engine->send(
            'get_variations',
            context: FakeContext::create(['product_id' => 'from_context']),
            body: PathPriorityTestBody::create(['product_id' => 'from_body']),
        );

        self::assertSame('/products/from_body/variations', $client->lastAction()?->getRawPath());
    }

    /**
     * @return array{0: IntegrationEngine, 1: FakeClient}
     */
    private function buildEngine(): array
    {
        $configPath = $this->tmpDir.'/integration.yaml';
        file_put_contents($configPath, <<<YAML
            get_variations:
                action: '{$this->actionClass()}'
                method: GET
                path: /products/{product_id}/variations
                body: 'IntegrationEngine\\Tests\\Core\\PathPriorityTestBody'
            YAML);

        $client = new FakeClient();
        $client->setResponse('fake_path_action', []);

        $engine = new IntegrationEngine(
            config: new YamlConfigAdapter($configPath),
            client: $client,
            cache: new FakeCache(),
            integrationName: 'test_integration',
        );

        return [$engine, $client];
    }

    private function actionClass(): string
    {
        return FakePathAction::class;
    }
}

final class PathPriorityTestBody implements ActionBodyInterface
{
    /** @param array<string, mixed> $data */
    private function __construct(private readonly array $data) {}

    /** @param array<string, mixed> $data */
    public static function create(array $data): self
    {
        return new self($data);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }
}
