<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Batch;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;

/**
 * A batch item after the engine has resolved config and authorization:
 * what a BatchClientInterface receives for each key. Dynamic auth has
 * already been converted to static auth on the action at this point.
 */
final readonly class PreparedRequest
{
    public function __construct(
        public AbstractAction $action,
        public ?ActionContextInterface $context,
        public ?RequestHeadersInterface $headers,
        public ?string $baseUrl = null,
    ) {}
}
