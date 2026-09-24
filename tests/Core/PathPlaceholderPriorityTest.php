<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Contract\Action\ActionBodyInterface;
use IntegrationEngine\Core\Exception\PathResolutionException;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Infrastructure\Adapter\YamlConfigAdapter;
use IntegrationEngine\Infrastructure\Http\SymfonyHttpClientAdapter;
use IntegrationEngine\Tests\Fake\FakeCache;
use IntegrationEngine\Tests\Fake\FakeClient;
use IntegrationEngine\Tests\Fake\FakeContext;
use IntegrationEngine\Tests\Fake\FakePathAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

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
    public function placeholderSuppliedOnlyByBodyCannotResolvePath(): void
    {
        [$engine, $client] = $this->buildEngine();

        $engine->send('get_variations', body: PathPriorityTestBody::create(['product_id' => 123]));

        $this->expectException(PathResolutionException::class);
        $client->lastAction()?->getPath($client->lastContext());
    }

    #[Test]
    public function placeholderSuppliedOnlyByContextIsResolved(): void
    {
        [$engine, $client] = $this->buildEngine();

        $engine->send('get_variations', context: FakeContext::create(['product_id' => 456]));

        self::assertSame('/products/{product_id}/variations', $client->lastAction()?->getRawPath());
        self::assertSame('/products/456/variations', $client->lastAction()->getPath($client->lastContext()));
    }

    #[Test]
    public function contextResolvesPathWithoutRemovingMatchingBodyField(): void
    {
        [$engine, $client] = $this->buildEngine();

        $engine->send(
            'get_variations',
            context: FakeContext::create(['product_id' => 'from_context']),
            body: PathPriorityTestBody::create(['product_id' => 'from_body']),
        );

        self::assertSame('/products/from_context/variations', $client->lastAction()?->getPath($client->lastContext()));
        self::assertSame(['product_id' => 'from_body'], $client->lastAction()->getBody()?->toArray());
    }

    #[Test]
    public function httpRequestUsesContextAndPreservesEntirePayload(): void
    {
        $http = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('PUT', $method);
            self::assertSame('https://api.example.com/products/42/variations', $url);
            self::assertIsString($options['body']);
            self::assertSame(['product_id' => ['nested' => true], 'name' => 'Ada'], json_decode($options['body'], true));

            return new MockResponse('{}');
        });
        $engine = $this->buildHttpEngine($http);

        $engine->send(
            'get_variations',
            context: FakeContext::create(['product_id' => 42]),
            body: PathPriorityTestBody::create(['product_id' => ['nested' => true], 'name' => 'Ada']),
        );

        self::assertSame(1, $http->getRequestsCount());
    }

    #[Test]
    public function missingContextParameterPreventsHttpRequestEvenWhenBodyContainsIt(): void
    {
        $http = new MockHttpClient(static function (): MockResponse {
            self::fail('An unresolved URL must never reach HTTP.');
        });
        $engine = $this->buildHttpEngine($http);

        $this->expectException(PathResolutionException::class);

        try {
            $engine->send('get_variations', body: PathPriorityTestBody::create(['product_id' => 42]));
        } finally {
            self::assertSame(0, $http->getRequestsCount());
        }
    }

    private function buildHttpEngine(MockHttpClient $http): IntegrationEngine
    {
        $this->buildEngine();
        $configPath = $this->tmpDir.'/integration.yaml';
        $config = file_get_contents($configPath);
        self::assertIsString($config);
        file_put_contents($configPath, str_replace('method: GET', 'method: PUT', $config));

        return new IntegrationEngine(
            config: new YamlConfigAdapter($configPath),
            client: new SymfonyHttpClientAdapter($http, 'https://api.example.com'),
            cache: new FakeCache(),
            integrationName: 'test_integration',
        );
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
