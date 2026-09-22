<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Lifecycle;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;

/** Small PSR-14 dispatcher for applications without an event framework. */
class LifecycleEventDispatcher implements EventDispatcherInterface
{
    /** @var array<class-string, list<callable>> */
    private array $subscribers = [];

    /** @param class-string $eventClass */
    public function subscribe(string $eventClass, callable $callable): void
    {
        $this->subscribers[$eventClass][] = $callable;
    }

    public function dispatch(object $event): object
    {
        foreach ($this->subscribers[$event::class] ?? [] as $callable) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }
            $callable($event);
        }

        return $event;
    }
}
