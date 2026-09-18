<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Lifecycle;

use IntegrationEngine\Core\Contract\Action\AbstractAction;

/**
 * Base interface for all integration engine lifecycle events.
 * Dispatched at key points in the request pipeline.
 */
interface IntegrationEngineEvent
{
    /**
     * The action being executed (or just executed).
     */
    public function action(): AbstractAction;

    /**
     * Integration name (e.g., 'stripe', 'shopify').
     */
    public function integrationName(): string;

    /**
     * Event timestamp (microtime).
     */
    public function timestamp(): float;
}
