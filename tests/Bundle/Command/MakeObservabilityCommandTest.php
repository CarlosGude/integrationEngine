<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Bundle\Command;

use IntegrationEngine\Bundle\Command\MakeObservabilityCommand;
use IntegrationEngine\Core\Lifecycle\ActionCompleted;
use IntegrationEngine\Core\Lifecycle\ActionFailed;
use IntegrationEngine\Core\Lifecycle\ActionStarted;
use IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher;
use IntegrationEngine\Tests\Fake\FakeLogger;
use IntegrationEngine\Tests\Fake\FakePathAction;
use IntegrationEngine\Tests\Fake\FakeTokenResponse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Filesystem\Filesystem;

final class MakeObservabilityCommandTest extends TestCase
{
    private string $projectDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/ie-observability-'.bin2hex(random_bytes(6));
        $this->filesystem = new Filesystem();
        $this->filesystem->mkdir($this->projectDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);
    }

    public function testGeneratedServiceCanBeLoadedAndLogsEachEventOnce(): void
    {
        $namespace = 'ObservabilityTest'.bin2hex(random_bytes(6));
        $this->filesystem->dumpFile($this->projectDir.'/composer.json', json_encode(['autoload' => ['psr-4' => [$namespace.'\\' => 'src/']]], JSON_THROW_ON_ERROR));
        $this->filesystem->dumpFile($this->projectDir.'/config/services.yaml', "services:\n    existing:\n        class: stdClass\nparameters:\n    marker: preserved\n");
        $tester = $this->generate('MY_API');
        $tester->assertCommandIsSuccessful();
        $path = $this->projectDir.'/src/Integration/MyApi/MyApiObservabilitySetup.php';
        self::assertFileExists($path);

        // Loading the generated PHP verifies syntax as well as runtime behaviour.
        require $path;
        $container = new ContainerBuilder();
        $logger = new FakeLogger();
        $dispatcher = new LifecycleEventDispatcher();
        $container->set('logger', $logger);
        $container->set(LifecycleEventDispatcher::class, $dispatcher);
        (new YamlFileLoader($container, new FileLocator($this->projectDir.'/config')))->load('services.yaml');
        self::assertTrue($container->hasDefinition('existing'));
        self::assertSame('preserved', $container->getParameter('marker'));
        $service = $container->get('app.my_api.observability');
        self::assertSame($namespace.'\Integration\MyApi\MyApiObservabilitySetup', $service::class);
        $action = FakePathAction::create('GET', '/orders');
        foreach (['other', 'my_api'] as $integration) {
            $dispatcher->dispatch(new ActionStarted($action, $integration, 1.0));
            $dispatcher->dispatch(new ActionCompleted($action, $integration, 1.0, new FakeTokenResponse([]), 6000.0));
            $dispatcher->dispatch(new ActionFailed($action, $integration, 1.0, new \RuntimeException('offline'), 100.0));
        }
        self::assertSame(['Integration action started', 'Integration action completed', 'Slow integration request detected', 'Integration action failed'], array_column($logger->all(), 'message'));
    }

    public function testMissingServicesPrintsManualEntryAndExistingClassIsPreserved(): void
    {
        $this->filesystem->dumpFile($this->projectDir.'/composer.json', 'invalid json');
        $tester = $this->generate('shop');
        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('services.yaml not found', $tester->getDisplay());
        self::assertStringContainsString('defining a private service alone does not activate observers', $tester->getDisplay());
        self::assertStringContainsString('same LifecycleEventDispatcher instance', $tester->getDisplay());
        self::assertStringContainsString('App\Integration\Shop\ShopObservabilitySetup', $tester->getDisplay());
        self::assertStringContainsString("arguments: ['@logger']", $tester->getDisplay());
        $path = $this->projectDir.'/src/Integration/Shop/ShopObservabilitySetup.php';
        $this->filesystem->dumpFile($path, '<?php // customized');
        self::assertSame(Command::FAILURE, $this->generate('shop')->getStatusCode());
        self::assertSame('<?php // customized', file_get_contents($path));
    }

    public function testExistingServiceEntryIsNotDuplicated(): void
    {
        $yaml = "services:\n  app.shop.observability:\n    class: stdClass\n";
        $this->filesystem->dumpFile($this->projectDir.'/config/services.yaml', $yaml);
        $tester = $this->generate('shop');
        $tester->assertCommandIsSuccessful();
        self::assertSame($yaml, file_get_contents($this->projectDir.'/config/services.yaml'));
        self::assertStringContainsString('already in services.yaml', $tester->getDisplay());
    }

    public function testInlineServicesMappingIsPreservedWithManualInstructions(): void
    {
        $yaml = "services: {}\n";
        $this->filesystem->dumpFile($this->projectDir.'/config/services.yaml', $yaml);
        $tester = $this->generate('shop');
        $tester->assertCommandIsSuccessful();
        self::assertSame($yaml, file_get_contents($this->projectDir.'/config/services.yaml'));
        self::assertStringContainsString('Add this manually', $tester->getDisplay());
        self::assertStringContainsString("arguments: ['@logger']", $tester->getDisplay());
    }

    public function testInvalidNamesDoNotWriteFiles(): void
    {
        foreach (['../outside', '9shop', 'my-api', ''] as $name) {
            self::assertSame(Command::INVALID, $this->generate($name)->getStatusCode());
        }
        self::assertDirectoryDoesNotExist($this->projectDir.'/src');
    }

    private function generate(string $integration): CommandTester
    {
        $tester = new CommandTester(new MakeObservabilityCommand($this->projectDir));
        $tester->execute(['integration' => $integration]);

        return $tester;
    }
}
