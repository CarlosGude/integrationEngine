<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use Psr\EventDispatcher\EventDispatcherInterface;

final class FakeEventDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }
}
