<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle;

use IntegrationEngine\Bundle\DependencyInjection\Compiler\IntegrationCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class IntegrationEngineBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Priority > 0: must run before FrameworkBundle's ProfilerPass (added
        // at the default priority 0), which bakes data_collector-tagged
        // services into the "profiler" service's add() calls at compile time.
        // FrameworkBundle is registered before this bundle in most apps, so
        // without an explicit priority its ProfilerPass would run first and
        // never see the collector this bundle registers conditionally below.
        $container->addCompilerPass(new IntegrationCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);
    }
}
