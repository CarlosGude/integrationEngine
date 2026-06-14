<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Infrastructure;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionBodyInterface;
use IntegrationEngine\Core\Contract\Auth\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Contract\Auth\StaticAuthorizationConfig;
use IntegrationEngine\Core\Exception\ActionNotFoundException;
use IntegrationEngine\Infrastructure\Adapter\YamlConfigAdapter;
use IntegrationEngine\Tests\Fake\FakePathAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class YamlConfigAdapterTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/ie_yaml_'.uniqid();
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->tmpDir);
    }

    // ── constructor validation ───────────────────────────────────────────────

    #[Test]
    public function constructorThrowsWhenFileDoesNotExist(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Integration config file not found');

        new YamlConfigAdapter($this->tmpDir.'/missing.yaml');
    }

    #[Test]
    public function constructorThrowsWhenFileIsEmpty(): void
    {
        $path = $this->writeConfig('');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is empty or invalid');

        new YamlConfigAdapter($path);
    }

    #[Test]
    public function constructorThrowsWhenFileIsNotAMap(): void
    {
        $path = $this->writeConfig('just a scalar');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is empty or invalid');

        new YamlConfigAdapter($path);
    }

    #[Test]
    public function constructorThrowsWhenActionEntryIsNotAMap(): void
    {
        $path = $this->writeConfig('get_employee: just_a_string');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Action "get_employee" must define a string "action" class');

        new YamlConfigAdapter($path);
    }

    #[Test]
    public function constructorThrowsWhenActionClassIsMissing(): void
    {
        $path = $this->writeConfig(<<<'YAML'
            get_employee:
                method: GET
                path: /employees
            YAML);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Action "get_employee" must define a string "action" class');

        new YamlConfigAdapter($path);
    }

    // ── getAction ────────────────────────────────────────────────────────────

    #[Test]
    public function getActionThrowsForUnknownAction(): void
    {
        $adapter = $this->buildAdapter(<<<'YAML'
            get_employee:
                action: '%s'
            YAML);

        $this->expectException(ActionNotFoundException::class);
        $this->expectExceptionMessage('Action [unknown] not found');

        $adapter->getAction('unknown');
    }

    #[Test]
    public function getActionBuildsActionWithConfiguredMethodAndPath(): void
    {
        $adapter = $this->buildAdapter(<<<'YAML'
            get_employee:
                action: '%s'
                method: GET
                path: /employees/{id}
            YAML);

        $action = $adapter->getAction('get_employee');

        self::assertInstanceOf(FakePathAction::class, $action);
        self::assertSame('GET', $action->getMethod());
        self::assertSame('/employees/{id}', $action->getRawPath());
        self::assertNull($action->getBody());
        self::assertNull($action->getAuthorization());
    }

    #[Test]
    public function getActionDefaultsToPostAndRootPath(): void
    {
        $adapter = $this->buildAdapter(<<<'YAML'
            get_employee:
                action: '%s'
            YAML);

        $action = $adapter->getAction('get_employee');

        self::assertSame('POST', $action->getMethod());
        self::assertSame('/', $action->getRawPath());
    }

    #[Test]
    public function getActionThrowsWhenActionClassDoesNotExist(): void
    {
        $adapter = $this->buildAdapterRaw(<<<'YAML'
            get_employee:
                action: 'App\Missing\NoSuchAction'
            YAML);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist or does not extend');

        $adapter->getAction('get_employee');
    }

    #[Test]
    public function getActionThrowsWhenActionClassDoesNotExtendAbstractAction(): void
    {
        $adapter = $this->buildAdapterRaw(<<<'YAML'
            get_employee:
                action: 'stdClass'
            YAML);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('does not exist or does not extend %s', AbstractAction::class));

        $adapter->getAction('get_employee');
    }

    // ── authorization ────────────────────────────────────────────────────────

    #[Test]
    public function getActionBuildsStaticAuthorization(): void
    {
        $adapter = $this->buildAdapter(<<<'YAML'
            get_employee:
                action: '%s'
                authorization:
                    type: static
                    token: sk_live_123
            YAML);

        $authorization = $adapter->getAction('get_employee')->getAuthorization();

        self::assertInstanceOf(StaticAuthorizationConfig::class, $authorization);
        self::assertSame('static', $authorization->type);
        self::assertSame(['token' => 'sk_live_123'], $authorization->params);
    }

    #[Test]
    public function getActionBuildsDynamicAuthorization(): void
    {
        $adapter = $this->buildAdapter(<<<'YAML'
            get_employee:
                action: '%s'
                authorization:
                    type: dynamic
                    action: fetch_token
                    token_field: access_token
                    ttl: 300
            YAML);

        $authorization = $adapter->getAction('get_employee')->getAuthorization();

        self::assertInstanceOf(DynamicAuthorizationConfig::class, $authorization);
        self::assertSame('fetch_token', $authorization->action);
        self::assertSame('access_token', $authorization->tokenField);
        self::assertSame(300, $authorization->ttl);
    }

    // ── body ─────────────────────────────────────────────────────────────────

    #[Test]
    public function getActionPassesProvidedBodyWhenDeclared(): void
    {
        $adapter = $this->buildAdapter(<<<'YAML'
            create_employee:
                action: '%s'
                body: 'IntegrationEngine\Tests\Infrastructure\YamlConfigTestBody'
            YAML);

        $body = YamlConfigTestBody::create(['name' => 'Ada']);

        $action = $adapter->getAction('create_employee', $body);

        self::assertSame($body, $action->getBody());
    }

    #[Test]
    public function getActionCreatesEmptyBodyWhenDeclaredAndNoneProvided(): void
    {
        $adapter = $this->buildAdapter(<<<'YAML'
            create_employee:
                action: '%s'
                body: 'IntegrationEngine\Tests\Infrastructure\YamlConfigTestBody'
            YAML);

        $action = $adapter->getAction('create_employee');

        self::assertInstanceOf(YamlConfigTestBody::class, $action->getBody());
        self::assertSame([], $action->getBody()->toArray());
    }

    #[Test]
    public function getActionThrowsWhenBodyClassDoesNotImplementInterface(): void
    {
        $adapter = $this->buildAdapter(<<<'YAML'
            create_employee:
                action: '%s'
                body: 'stdClass'
            YAML);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('must implement %s', ActionBodyInterface::class));

        $adapter->getAction('create_employee');
    }

    #[Test]
    public function getActionThrowsWhenBodyProvidedButNotDeclared(): void
    {
        $adapter = $this->buildAdapter(<<<'YAML'
            get_employee:
                action: '%s'
            YAML);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not declare a body in its YAML config');

        $adapter->getAction('get_employee', YamlConfigTestBody::create(['name' => 'Ada']));
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** Builds an adapter from a YAML template whose %s is replaced by the fake action FQCN. */
    private function buildAdapter(string $yamlTemplate): YamlConfigAdapter
    {
        return $this->buildAdapterRaw(\sprintf($yamlTemplate, FakePathAction::class));
    }

    private function buildAdapterRaw(string $yaml): YamlConfigAdapter
    {
        return new YamlConfigAdapter($this->writeConfig($yaml));
    }

    private function writeConfig(string $yaml): string
    {
        $path = $this->tmpDir.'/'.uniqid().'.yaml';
        file_put_contents($path, $yaml);

        return $path;
    }
}

// ──────────────────────────────────────────────
// Inline fake
// ──────────────────────────────────────────────

final class YamlConfigTestBody implements ActionBodyInterface
{
    /** @param array<string, mixed> $data */
    private function __construct(private readonly array $data) {}

    public static function create(array $data): self
    {
        return new self($data);
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
