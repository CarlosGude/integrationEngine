<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Connection;

/**
 * Resolves application-specific runtime connection info into the engine's
 * ConnectionCredentials. Implement this in the integration/application that
 * knows how to interpret $connection — e.g. loading a tenant's stored
 * base_url and API key from your own database. The engine never inspects
 * $connection itself; it only consumes the resolved credentials.
 *
 * Registered per integration via the `connection_resolver` bundle config
 * key. Without one configured, IntegrationEngine::send()/sendMany() reject
 * any non-null $connection argument.
 */
interface ConnectionResolverInterface
{
    public function resolve(mixed $connection): ConnectionCredentials;
}
