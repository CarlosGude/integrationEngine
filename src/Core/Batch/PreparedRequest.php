<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Batch;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;

/**
 * A batch item after the engine has resolved config, connection and
 * authorization: what a BatchClientInterface receives for each key.
 * Dynamic auth has already been converted to static auth on the action
 * at this point.
 *
 * $baseUrl and $cacheDiscriminator can differ: $baseUrl is the real
 * per-item target URL (used to group/dispatch and, absent a resolved
 * connectionId, to namespace the auth cache too); $cacheDiscriminator is
 * carried separately so a retry after 401 re-derives the token against
 * the same cache entry the original attempt used, even when a
 * ConnectionResolverInterface resolved a connectionId distinct from
 * $baseUrl (e.g. several connections sharing one endpoint).
 */
final readonly class PreparedRequest
{
    public function __construct(
        public AbstractAction $action,
        public ?ActionContextInterface $context,
        public ?RequestHeadersInterface $headers,
        public ?string $baseUrl = null,
        public ?string $cacheDiscriminator = null,
    ) {}
}
