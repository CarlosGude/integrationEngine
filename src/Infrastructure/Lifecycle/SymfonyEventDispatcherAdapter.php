<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Lifecycle;

use IntegrationEngine\Core\Lifecycle\LifecycleEventDispatcher;
use Psr\EventDispatcher\StoppableEventInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/** Bridges local subscriptions and an application's Symfony dispatcher. */
final class SymfonyEventDispatcherAdapter extends LifecycleEventDispatcher
{
    public function __construct(private EventDispatcherInterface $dispatcher) {}

    public function dispatch(object $event): object
    {
        parent::dispatch($event);
        if (!$event instanceof StoppableEventInterface || !$event->isPropagationStopped()) {
            $this->dispatcher->dispatch($event);
        }

        return $event;
    }
}
