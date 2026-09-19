<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\DependencyInjection\Compiler;

use IntegrationEngine\Core\Contract\Client\ClientAdapterInterface;

/**
 * Inputs IntegrationCompilerPass resolves once per compilation and shares
 * across every integration it wires.
 */
final readonly class IntegrationWiringContext
{
    /**
     * @param array<string, class-string<ClientAdapterInterface>> $adapterMap
     * @param array<string, true>                                 $registeredMiddlewares
     * @param array<string, true>                                 $registeredRequestMiddlewares
     */
    public function __construct(
        public MiddlewareResolver $middlewareResolver,
        public array $adapterMap,
        public array $registeredMiddlewares,
        public array $registeredRequestMiddlewares,
    ) {}
}
