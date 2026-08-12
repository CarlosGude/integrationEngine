<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Client;

/**
 * Optional capability for middlewares whose behavior must vary by the
 * active base URL — e.g. cache-key namespacing, so that one integration
 * serving several tenants via a per-call baseUrl never shares a cache
 * entry between tenants. When MiddlewareClient::withBaseUrl() runs,
 * middlewares implementing this are rebuilt with the new baseUrl; others
 * are reused unchanged.
 */
interface BaseUrlAwareMiddlewareInterface
{
    /**
     * Returns a new instance scoped to the given base URL, without
     * mutating the original.
     */
    public function withBaseUrl(string $baseUrl): static;
}
