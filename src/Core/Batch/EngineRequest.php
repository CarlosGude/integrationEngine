<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Batch;

use IntegrationEngine\Core\Contract\ActionBodyInterface;
use IntegrationEngine\Core\Contract\ActionContextInterface;
use IntegrationEngine\Core\Contract\RequestHeadersInterface;

/**
 * One request inside a batch — the same four values accepted by
 * IntegrationEngine::send(), packaged as an immutable value object
 * so a batch can mix different actions, contexts and bodies.
 */
final readonly class EngineRequest
{
    private function __construct(
        public string $actionName,
        public ?ActionContextInterface $context,
        public ?ActionBodyInterface $body,
        public ?RequestHeadersInterface $headers,
    ) {}

    public static function create(
        string $actionName,
        ?ActionContextInterface $context = null,
        ?ActionBodyInterface $body = null,
        ?RequestHeadersInterface $headers = null,
    ): self {
        return new self($actionName, $context, $body, $headers);
    }
}
