<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Client;

/**
 * Extension point for concerns that need the fully-built request — method,
 * URL, headers, body — rather than the pre-resolution AbstractAction that
 * AbstractClientMiddleware sees. The motivating case is request signing
 * (e.g. OAuth 1.0a) where the signature is computed over the final request.
 *
 * Registered per integration via the `request_middlewares` bundle config
 * key (ordered list, first entry outermost — same convention as
 * `middlewares:`). The built-in REST, GraphQL and form-encoded adapters support this; a fully custom client_service is
 * responsible for handling signing itself if it needs it.
 *
 * Implementations must not hold connection-specific state (e.g. a signing
 * secret) as instance state shared across calls — the request already
 * carries everything needed to sign it; secrets belong in the middleware's
 * own injected configuration, resolved consistently for every call.
 */
interface RequestMiddlewareInterface
{
    /**
     * Call $next($request) to continue the chain and reach the transport,
     * optionally after modifying $request. Not calling it short-circuits
     * the request — return a value directly to fake a response, or throw
     * to reject it. The transport's own exceptions (e.g. RequestResponseException)
     * propagate through $next() like any other exception.
     *
     * @param callable(Request): array{body: array<mixed>, headers: array<string, list<string>>, statusCode?: int} $next
     *
     * @return array{body: array<mixed>, headers: array<string, list<string>>, statusCode?: int}
     */
    public function handle(Request $request, callable $next): array;
}
