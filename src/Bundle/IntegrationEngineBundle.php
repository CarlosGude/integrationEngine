<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle;

use IntegrationEngine\Bundle\DependencyInjection\Compiler\IntegrationCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class IntegrationEngineBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new IntegrationCompilerPass());
    }
}
