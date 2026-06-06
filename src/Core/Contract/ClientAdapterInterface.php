<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

/**
 * Implement this interface to register a custom HTTP adapter.
 * The bundle will discover it automatically via Symfony's DI tag system.
 *
 * The value returned by getClientType() is used in integration_engine.yaml:
 *
 *   integration_engine:
 *     integrations:
 *       my_api:
 *         client: my_custom_type
 *
 * Project adapters always take precedence over bundle built-ins.
 * Implementing this interface with an existing type (e.g. "rest") replaces
 * the bundle's default adapter for that type.
 */
interface ClientAdapterInterface extends ClientInterface
{
    /**
     * The identifier used in the `client:` key of integration_engine.yaml.
     * Built-in values: "rest", "graphql".
     */
    public static function getClientType(): string;

    /**
     * Whether actions for this adapter need a path declaration.
     * REST: true. GraphQL/SOAP: false.
     */
    public static function requiresPath(): bool;

    /**
     * Whether actions for this adapter need an HTTP method declaration.
     * REST: true. GraphQL/SOAP: false.
     */
    public static function requiresMethod(): bool;
}