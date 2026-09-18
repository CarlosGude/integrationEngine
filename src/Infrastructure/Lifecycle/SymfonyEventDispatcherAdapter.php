<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Lifecycle;

use IntegrationEngine\Core\Lifecycle\IntegrationEngineEvent;
use IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Adapter: dispatch IntegrationEngine events via Symfony EventDispatcher.
 * Use this if your app already uses Symfony events.
 *
 * Usage in services.yaml:
 *   IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher:
 *     class: IntegrationEngine\Infrastructure\Lifecycle\SymfonyEventDispatcherAdapter
 *     arguments:
 *       - '@event_dispatcher'
 */
final class SymfonyEventDispatcherAdapter extends LifecycleEventDispatcher
{
    public function __construct(private EventDispatcherInterface $dispatcher)
    {
        parent::__construct();
    }

    public function dispatch(IntegrationEngineEvent $event): void
    {

        parent::dispatch($event);
        $this->dispatcher->dispatch($event, $event::class);
    }
}
