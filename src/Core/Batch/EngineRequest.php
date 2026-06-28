<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Batch;

use IntegrationEngine\Core\Contract\Action\ActionBodyInterface;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;

/**
 * One request inside a batch — the same four values accepted by
 * IntegrationEngine::send(), packaged as an immutable value object
 * so a batch can mix different actions, contexts and bodies.
 */
final readonly class EngineRequest
{
    public function __construct(
        public string $actionName,
        public ?ActionContextInterface $context = null,
        public ?ActionBodyInterface $body = null,
        public ?RequestHeadersInterface $headers = null,
        public ?string $baseUrl = null,
    ) {}
}
