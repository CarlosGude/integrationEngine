<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Connection;

use IntegrationEngine\Core\Contract\Auth\AuthorizationConfig;

/**
 * What a ConnectionResolverInterface resolves a runtime connection to.
 * Every field is optional: a resolver only needs to set what actually
 * varies per connection — e.g. only $baseUrl if every connection shares
 * one static AuthorizationConfig from YAML, or only $authorization if
 * every connection hits the same URL.
 */
final readonly class ConnectionCredentials
{
    /**
     * $connectionId, when set, namespaces dynamic-auth token cache entries
     * instead of $baseUrl. Required whenever several connections could
     * resolve to the same $baseUrl (e.g. one shared multi-tenant endpoint
     * distinguished only by credentials) — without it, those connections
     * would collide on the same cached token. Must be a stable, non-secret
     * identifier: never a consumer key/secret/access token.
     */
    public function __construct(
        public ?string $baseUrl = null,
        public ?AuthorizationConfig $authorization = null,
        public ?string $connectionId = null,
    ) {}
}
