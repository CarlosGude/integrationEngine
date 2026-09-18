<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Lifecycle;

/**
 * Minimal dispatcher for integration engine lifecycle events.
 * Applications can plug in Symfony EventDispatcher or any PSR-14 dispatcher.
 *
 * Usage:
 *   $dispatcher = new LifecycleEventDispatcher();
 *   $dispatcher->subscribe(ActionCompleted::class, function(ActionCompleted $event) {
 *       \Psr\Log\LogLevel::info("Action completed: {$event->action()->getName()}");
 *   });
 */
class LifecycleEventDispatcher
{
    /** @var array<class-string, list<callable>> */
    private array $subscribers = [];

    /**
     * Subscribe to an event type.
     * $callable receives the event as its only argument.
     *
     * @param class-string $eventClass
     * @param callable $callable
     */
    public function subscribe(string $eventClass, callable $callable): void
    {
        if (!isset($this->subscribers[$eventClass])) {
            $this->subscribers[$eventClass] = [];
        }
        $this->subscribers[$eventClass][] = $callable;
    }

    /**
     * Dispatch an event to all subscribers.
     */
    public function dispatch(IntegrationEngineEvent $event): void
    {
        $eventClass = $event::class;
        if (!isset($this->subscribers[$eventClass])) {
            return;
        }

        foreach ($this->subscribers[$eventClass] as $callable) {
            $callable($event);
        }
    }
}
