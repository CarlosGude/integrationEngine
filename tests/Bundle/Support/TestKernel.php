<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Bundle\Support;

use IntegrationEngine\Bundle\IntegrationEngineBundle;
use IntegrationEngine\Core\Registry\IntegrationRegistry;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    public function __construct(
        private readonly array $integrationEngineConfig = [],
    ) {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        yield new IntegrationEngineBundle();
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('integration_engine', $this->integrationEngineConfig);

            $container->addCompilerPass(new class implements CompilerPassInterface {
                public function process(ContainerBuilder $container): void
                {
                    // Register a fake http_client so SymfonyHttpClientAdapter can be wired
                    if (!$container->has('http_client')) {
                        $container->setDefinition(
                            'http_client',
                            (new Definition(FakeHttpClient::class))->setPublic(true)
                        );
                    }

                    // Make registry accessible via ->get() in tests
                    $container->getDefinition(IntegrationRegistry::class)->setPublic(true);
                }
            });
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/integration_engine_test/'.spl_object_id($this).'/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/integration_engine_test/'.spl_object_id($this).'/logs';
    }
}
