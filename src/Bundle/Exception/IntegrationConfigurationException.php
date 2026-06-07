<?php

declare(strict_types=1);

namespace IntegrationEngine\Bundle\Exception;

/**
 * Thrown during container compilation when an integration is misconfigured.
 * Extends \LogicException because misconfiguration is a programming error,
 * not a runtime condition — it should never reach production.
 */
final class IntegrationConfigurationException extends \LogicException
{
    public static function missingConfigPath(string $integrationName): self
    {
        return new self(\sprintf(
            'Integration "%s" must define "config_path". '
            .'Use "php bin/console make:integration" to generate it automatically.',
            $integrationName,
        ));
    }

    public static function unknownClientType(string $clientType, string $integrationName, string $registered): self
    {
        return new self(\sprintf(
            'Unknown client type "%s" for integration "%s". Registered types: %s.',
            $clientType,
            $integrationName,
            $registered ?: 'none',
        ));
    }
}
